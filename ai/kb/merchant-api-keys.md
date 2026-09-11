# Merchant account API keys

**Status:** Implemented (2026-08-01; dual credentials 2026-08-02) — ADR-015  
**ADR:** ADR-015 in [`decisions.md`](./decisions.md)  
**Audience:** Engineering

---

## 1. Locked decisions

| # | Decision | Choice |
|---|----------|--------|
| K1 | Ownership | **Account-scoped** key (not user-scoped) |
| K2 | Permission model | **Single shared config** `api-key → Set<Permission>` for all accounts |
| K3 | Extensibility | Config-driven (YAML); extend by adding permissions — no per-key scopes in v1 |
| K4 | Auth surface | API key accepted on the **same routes** as dashboard JWT |
| K5 | v1 capability | **Contacts-focused**, **read + write** (MCP-ready) |
| K6 | Secret handling | Store **hash of private secret only**; plaintext secret shown once at create |
| K7 | Credential shape | **Public key + private secret** (Klaviyo-style), both required on each request |
| K8 | Dashboard JWT | Contact controllers are **not** gated by `@RequiresPermission(CONTACTS)` for UI safety; API keys are limited by a **path allowlist** instead |

---

## 2. How auth works

```text
Client (HTTP or MCP)
  X-Adflipr-Api-Key: pk_adflipr_<id>
  X-Adflipr-Api-Secret: sk_adflipr_<secret>
        OR
  Authorization: Basic base64(pk:sk)
        ↓
ApiKeyAuthenticationFilter (before JWT)
  → lookup by publicKey, verify secret hash
  → accountId + AuthContext (Role.API_KEY, config permissions)
  → path must match security.api-key.allowed-path-prefixes  (else 403)
        ↓
Controller / service (JWT users unchanged — no extra CONTACTS annotation required)
```

**Legacy:** single `adflipr_…` Bearer / `X-Adflipr-Api-Key` still accepted for keys created before dual credentials.

**Why not annotate ContactController for JWT?** Adding `@RequiresPermission(CONTACTS)` on contact APIs would also enforce CONTACTS for dashboard sessions and risk 403s for roles/UI paths that previously worked without that gate. v1 instead scopes **API keys only** via YAML `security.api-key.allowed-path-prefixes`.

---

## 3. v1 config

In `service/src/main/resources/role-permission-mapping.yml`:

```yaml
security:
  api-key:
    permissions:
      - VIEWER_ACCESS
      - CONTACTS
    allowed-path-prefixes:
      - /api/v1/contact
      - /api/v1/tag
```

---

## 4. Data model

Collection: `account_api_key`

| Field | Notes |
|-------|--------|
| `publicKey` | `pk_adflipr_` + 12 hex — stored plaintext, unique |
| `keyHash` | HMAC-SHA256 of `sk_adflipr_…` secret |
| `keyPrefix` | Display (= publicKey for new rows) |
| `status` | `ACTIVE` \| `REVOKED` |

Create returns `publicKey` + `apiSecret` **once**. List returns public key + metadata only.

**Manage APIs** (JWT + `SETTINGS` — OWNER/ADMIN):

- `GET/POST/DELETE /api/v1/account/api-keys`

**Call APIs** (contacts v1):

```http
X-Adflipr-Api-Key: pk_adflipr_…
X-Adflipr-Api-Secret: sk_adflipr_…
```

Hash secret: `adflipr.api-key.hash-secret` (falls back to `wp.integration.token-hash-secret`).

---

## 5. Product UI

**Settings → API keys** (OWNER/ADMIN)

- Create (name) → show public key + private secret once
- List public keys
- Revoke

---

## 6. MCP

Same account credentials authenticate MCP tools that call Adflipr HTTP APIs. Prefer dual headers.

---

## 7. Non-goals (v1)

- Per-key custom scopes
- Annotating all contact controllers with CONTACTS for JWT (use API-key path allowlist instead)
- Platform admin key reuse (`X-Adflipr-Admin-Key`) for merchants

---

## 8. References

- ADR-015 — `ai/kb/decisions.md`
- `RolePermissionProperties.isApiKeyPathAllowed`, `ApiKeyAuthenticationFilter`
- `role-permission-mapping.yml`
