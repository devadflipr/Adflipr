---
name: elasticsearch-mapping-update
description: >-
  Adflipr Elasticsearch workflow when mappings or index settings change: versioned
  physical indices (_dev/_prod), reindex from old to new, atomic alias swap.
  Use when editing elasticsearch/mappings or settings (repo root), fixing mapping
  conflicts, upgrading ES index definitions, or running index migrations.
---

# Skill: Elasticsearch mapping / settings update (Adflipr)

## Context (read first)

- **What the app calls:** `IndexNameResolver` resolves **base** names (e.g. `tag`) to **`{base}_{env}`** with `env` ∈ `dev` | `prod` — e.g. `tag_dev`, `tag_prod`. See `service/docs/ELASTICSEARCH_INDEX_NAMING.md`.
- **Physical indices** are usually **versioned** and sit **behind** those names via **aliases** (e.g. `tag_dev` → `tag_dev_v2`). For a **mapping change**, create a **new** physical index; do **not** rely on in-place mapping updates for incompatible changes.
- **Canonical files (monorepo root):** `elasticsearch/registry.json`, `elasticsearch/mappings/*.json`, `elasticsearch/settings/*.json`. See `service/docs/ELASTICSEARCH_LOCAL_OPERATIONS_AND_TROUBLESHOOTING.md` and `service/docs/ELASTICSEARCH_REINDEX_DEV_PROD.md` for curl patterns.

## Naming: new physical index

Use a **new** physical name so the cluster keeps history and rollback is possible.

- **Recommended for mapping migrations:** `{base}_{env}_v{YYYYMMDD}` (e.g. `tag_dev_v20260403`, `tag_prod_v20260403`).
- **Alternative:** incremental `vN` (e.g. `tag_dev_v3`) if that is already your cluster convention — stay consistent per index family.

The **alias** name stays **`{base}_{env}`** (`tag_dev` / `tag_prod`); only the backing index changes.

## Workflow (order matters)

**Reindex must complete before the alias points at the new index**, or the app will query an empty or partial index.

Copy this checklist and track progress:

```
- [ ] 1. Update mapping/settings JSON in `elasticsearch/` at repo root (and registry if needed)
- [ ] 2. Resolve current physical index behind alias
- [ ] 3. Create new empty index (new name, new mapping/settings)
- [ ] 4. Run _reindex from old physical → new physical; wait for completion
- [ ] 5. Verify doc counts / spot-check queries
- [ ] 6. Atomically swap alias (remove from old, add to new) in one _aliases call
- [ ] 7. (Optional) After validation, delete old physical index
```

### Step 1 — Edit repo artifacts

- Update `elasticsearch/mappings/<base>.json` and, if present, `elasticsearch/settings/<base>.json`.
- If multiple aliases reference one physical index (e.g. `workflowstate_dev` and `wfs_dev`), plan **one** reindex and **all** alias updates together.

### Step 2 — Resolve current physical index

Substitute host, auth, and names.

```bash
ES=http://localhost:9200
ALIAS=tag_dev
curl -s "${ES}/_alias/${ALIAS}?pretty"
```

Response keys are **current physical** index names. Record the one (or list) that backs `ALIAS`.

### Step 3 — Create the new index

`PUT` the new physical index (e.g. `tag_dev_v20260403`) with body = merged **settings** + **mappings** from `elasticsearch/` at repo root (same shape as Elasticsearch create-index API).

Ensure cluster prerequisites exist (e.g. ingest pipeline `convert-mongo-date` if referenced in settings).

### Step 4 — Reindex

```bash
curl -X POST "${ES}/_reindex?wait_for_completion=true" \
  -H "Content-Type: application/json" \
  -d '{
    "source": { "index": "<OLD_PHYSICAL>" },
    "dest": { "index": "<NEW_PHYSICAL>" }
  }'
```

For large indices, use `wait_for_completion=false`, capture task id from response, poll `_tasks/<id>` until complete. Optionally use `slices` for parallelism per ES docs.

### Step 5 — Verify

- `POST /<new_index>/_refresh` before `_count` if you compare immediately after `_reindex` (short visibility lag on some nodes).
- Compare `_count` on old vs new, or use `_cat/indices` / sampling searches.
- Fix any mapping rejections during reindex (check task failures / logs) before swapping.

### Step 6 — Atomic alias swap

One `POST _aliases` request: **remove** alias from old index, **add** to new index.

```bash
curl -X POST "${ES}/_aliases" -H "Content-Type: application/json" -d '{
  "actions": [
    { "remove": { "index": "<OLD_PHYSICAL>", "alias": "tag_dev" } },
    { "add":    { "index": "<NEW_PHYSICAL>", "alias": "tag_dev" } }
  ]
}'
```

Repeat `remove`/`add` pairs in the **same** JSON for every alias that pointed at `OLD_PHYSICAL` (e.g. both `workflowstate_dev` and `wfs_dev`).

Do **prod** (`tag_prod`, etc.) in a separate maintenance window with the same pattern.

### Step 7 — Cleanup (optional)

After soak time, `DELETE` `<OLD_PHYSICAL>` only if no alias and no compliance need to retain it.

## Agent / operator rules

- Never swap the alias to the new index **before** reindex finishes.
- Prefer **one** `_aliases` request for remove+add per alias to avoid a window where the alias is missing.
- Keep **dev** and **prod** migrations explicit; do not assume one reindex updates both unless you intentionally run two destinations.
- If the app index name equals the physical index (no alias), create alias strategy or schedule downtime — see `ELASTICSEARCH_REINDEX_DEV_PROD.md` (`user` edge case).

## Related paths

| Doc / code | Role |
|------------|------|
| `service/docs/ELASTICSEARCH_INDEX_NAMING.md` | `_dev` / `_prod`, `IndexNameResolver` |
| `service/docs/ELASTICSEARCH_REINDEX_DEV_PROD.md` | Reindex + alias examples |
| `service/docs/ELASTICSEARCH_LOCAL_OPERATIONS_AND_TROUBLESHOOTING.md` | Export mappings, `_alias`, health |
| `elasticsearch/aliases/aliases-actions.TEMPLATE.json` | Alias action shape |
| `com.goveca.recruit.ad.util.IndexNameResolver` | Runtime index names |
