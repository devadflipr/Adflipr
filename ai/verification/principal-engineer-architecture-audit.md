# General architecture & implementation audit playbook (canonical)

**Status:** **The only canonical audit methodology for Adflipr.** Every architecture, integration, feature, production-behavior, security, or platform-parity audit must follow this playbook — same trace model, document suite, and evidence rules.

**Aliases:** Principal Engineer audit · general auditor · production audit · deep discovery audit

**Purpose:** Evidence-based production audit — **not** a code-style review.  
**Audience:** Principal Engineers, staff engineers, agents performing deep discovery.  
**Canonical location:** `ai/verification/principal-engineer-architecture-audit.md`  
**Invocation:** [`ai/prompts/principal-engineer-audit-template.md`](../prompts/principal-engineer-audit-template.md)  
**Cursor rule:** `.cursor/rules/principal-engineer-audit.mdc`  
**Example completed audit:** Commerce transaction suite — [`TRANSACTION_INGESTION_ARCHITECTURE.md`](../../service/docs/TRANSACTION_INGESTION_ARCHITECTURE.md), [`COMMERCE_TRANSACTION_INTEGRATION_AUDIT.md`](../../service/docs/COMMERCE_TRANSACTION_INTEGRATION_AUDIT.md), [`TRANSACTION_INGESTION_ADR.md`](../../service/docs/TRANSACTION_INGESTION_ADR.md)  
**ADR:** `ai/kb/decisions.md` ADR-006

**When to use this vs other verification docs:**

| Document | Scope |
|----------|--------|
| **This playbook (general auditor)** | **Any** audit: ingress → persistence → async → downstream → security → ops. Default when the user says “audit”. |
| **`architecture-checklist.md`** | Email Builder post-refactor gate only (blocks, traits, hydration) — **not** a substitute for a full audit. |
| **`code-review-checklist.md`** | PR/patch review — style, safety, KB alignment — **not** a substitute for a full audit. |
| **Phase acceptance notes** (`ai/kb/*-audit.md`) | Historical track records; new work must still follow **this** playbook for full audits. |

**Instruct the AI:** *“Audit {SUBJECT} using the general auditor: `ai/verification/principal-engineer-architecture-audit.md`. Read all relevant code. Label findings with ✅ Verified / ⚠ Impact / 💡 Recommendation and confidence (High/Medium/Low).”*

---

## Evidence taxonomy (mandatory in every audit)

Use **three visual categories** throughout findings tables and risk registers. Never mix them in the same table cell without labeling.

| Label | Meaning | Example |
|-------|---------|---------|
| **✅ Verified** | Observed directly in code (class, method, file). | `AutomationEventService` switch has no `ORDER_CREATED` case. |
| **⚠ Impact** | Production or business effect **of** a verified behavior. | Merchants configuring “Order Created” automations see zero enrollments. |
| **💡 Recommendation** | Proposed change — **only after** ✅ and ⚠ are documented. | Emit `ORDER_CREATED` on ORDER `COMPLETED` insert. |

**Rule:** No 💡 without preceding ✅. ⚠ may reference ✅ by ID (e.g. F-08).

---

## Confidence levels (mandatory)

Attach a confidence level to every significant finding. Use consistently — not only for “Not verified.”

| Level | Meaning | When to use |
|-------|---------|-------------|
| **High** | Verified directly in code at cited path; behavior deterministic from reading. | Single-class logic, enum values, explicit early returns. |
| **Medium** | Verified across multiple call paths in repo; production config/timing not confirmed. | Cron bundled in `initiate` but external schedule **not verified**. |
| **Low** | Inferred from behavior or docs; deployment/ops not confirmed in repo. | “Production cron runs every 5 min” without infra evidence. |

**Not verified:** Use when evidence is insufficient — equivalent to **Low** with explicit gap stated.

---

## Finding priority taxonomy

Separate **product correctness** from **engineering debt** in executive summary and roadmap.

| Class | Definition | Examples (commerce) |
|-------|------------|---------------------|
| **Product correctness** | Wrong merchant-visible behavior, broken automations, incorrect metrics, data loss. | `ORDER_CREATED` not emitted; recovered revenue triple contract; `contactId` gate. |
| **Engineering debt** | Maintainability, duplication, naming, class size — may not change happy-path behavior today. | `ShopifyService` size; triplicated mapping; `cardId` typo. |
| **Security / compliance** | Trust boundary, auth, tenant isolation. | Unused Woo HMAC; hardcoded pixel token. |
| **Operational** | Observability, scaling, cron reliability. | No metrics; unbounded UTM cron. |

Roadmap tables should tag each item with **Class** + **P0–P3**.

---

## Document suite (split for maintainability)

Large audits **must** split into three linked documents when the combined output exceeds ~800 lines or serves as long-term reference:

| Document | Role | Typical path |
|----------|------|--------------|
| **Architecture KB** | Stable reference: data model, lifecycle, state machines, entry points, downstream contracts. Facts only (✅). | `service/docs/<FEATURE>_ARCHITECTURE.md` or `docs/<FEATURE>_ARCHITECTURE.md` |
| **Audit findings** | Gap analysis, failure catalog, smells, test gaps, KB drift. ✅ + ⚠. | `service/docs/<FEATURE>_AUDIT.md` |
| **ADR** | Architecture decisions: context, options, recommendation, consequences. 💡 with decision status. | `service/docs/<FEATURE>_ADR.md` |

**Hub pattern:** The audit file links to architecture + ADR at the top. Do **not** duplicate full architecture prose in the audit file — cross-reference.

**Compressed AI pointer:** `ai/kb/<topic>.md` — links to all three.

**Reference implementation:** Commerce transaction ingestion — `TRANSACTION_INGESTION_ARCHITECTURE.md`, `COMMERCE_TRANSACTION_INTEGRATION_AUDIT.md`, `TRANSACTION_INGESTION_ADR.md`.

---

## Architecture invariants (mandatory — Architecture KB)

Every subsystem audit must declare **invariants** — rules that must remain true for the system to be correct. Future reviews check whether invariants still hold.

**Template (adapt per feature):**

| # | Invariant | ✅ Current state | Violation symptom |
|---|-----------|------------------|-------------------|
| I-1 | Exactly one write path to canonical store | | Duplicate or divergent writes |
| I-2 | Every persisted entity has a single owning service | | Split write ownership |
| I-3 | Mapping is deterministic for same input | | Platform drift, bulk ≠ live |
| I-4 | Async processing is idempotent or explicitly deduped | | Duplicate side effects |
| I-5 | Platform differences are explicit and documented | | Silent parity assumptions |
| I-6 | Failures are observable (log, metric, or surfaced error) | | Silent skip |
| I-7 | Analytics derive from canonical persisted state | | Multiple conflicting predicates |

**Placement:** `service/docs/<FEATURE>_ARCHITECTURE.md` — section **Architecture Invariants**, near top after overview.

**Review rule:** Any 💡 recommendation that breaks an accepted invariant requires a new ADR with migration plan.

---

## Non-goals (mandatory — Audit findings)

Before recommendations, state what the audit **does not** evaluate. Prevents scope creep and reviewer confusion.

**Template:**

```markdown
## Non-goals

### This audit does not evaluate
- …

### This audit intentionally excludes
- …
```

**Placement:** Audit findings doc — after scope / executive summary, **before** recommendations.

---

## Assumptions (mandatory — Audit findings)

Production behavior often depends on infrastructure not in the repo. List assumptions explicitly — **not** as verified facts.

**Template:**

| Assumption | Confidence | Evidence in repo | If wrong |
|------------|------------|------------------|----------|
| External cron invokes `PATCH /api/v1/cron/initiate` on schedule | Low | Endpoint exists; no schedule config | Scheduler delays; abandonment timing wrong |
| Production Mongo has index on `(status, nextProcessTime)` | Low | Not in entity annotations | Scheduler scan latency |

**Rule:** Assumptions use **Low** or **Medium** confidence unless infra-as-code is in repo.

**Placement:** Audit findings doc — after non-goals or alongside scope.

---

## Decision criteria (mandatory — ADR doc)

Before individual ADRs, state how options are evaluated. Makes 💡 recommendations transparent.

**Default criteria (weight per ADR as needed):**

| Criterion | Question |
|-----------|----------|
| **Correctness** | Does it fix merchant-visible wrong behavior or data loss? |
| **Operational simplicity** | Fewer moving parts, clearer runbooks? |
| **Performance** | Acceptable at current and 10× scale? |
| **Scalability** | Survives more accounts, webhooks, or documents? |
| **Maintainability** | Reduces duplication and onboarding cost? |
| **Migration cost** | Data migration, downtime, backfill effort? |
| **Backward compatibility** | Breaks existing automations, APIs, or reports? |

**Placement:** ADR doc — first section after intro, before individual ADR-TI-### records.

---

## Decision log (mandatory — ADR doc)

Extend ADRs with a **Decision Log** table for architecture evolution over time.

**Status values:** `Accepted` · `Proposed` · `Superseded` · `Rejected` · `Deferred`

**Template:**

| ID | Decision | Status | Reason (one line) | Date | Owner |
|----|----------|--------|-------------------|------|-------|
| TI-001 | Preserve single write funnel | Accepted | Correct invariant; proven in prod | 2026-07-11 | Engineering |
| TI-002 | Woo lifecycle model | Proposed | Awaiting product call on parity | 2026-07-11 | — |

**Rules:**

- One row per ADR; update status when decisions change.
- **Superseded** rows link to replacement ADR ID.
- **Owner** = team or role accountable for implementation (not required for Proposed).

**Placement:** ADR doc — **last section** (after individual ADR narratives); summary table mirrors detail below.

---

## Primary objectives

Understand:

- End-to-end request lifecycle
- Data lifecycle
- Ownership of every major component
- Source of truth
- Platform-specific behavior
- Mapping logic
- Persistence model
- Async processing
- Scheduler / background jobs
- Event flow
- Change streams
- Downstream consumers
- Analytics impact
- Automation impact
- Failure scenarios
- Security
- Operational behavior

**Never assume.** Everything must be verified from code.

---

## Audit principles — trace the full chain

Always trace execution from:

```
Ingress
  ↓
Validation
  ↓
Routing
  ↓
Business Logic
  ↓
Mapping
  ↓
Persistence
  ↓
Background Processing
  ↓
Downstream Consumers
  ↓
Analytics
  ↓
Automation
  ↓
UI / API Consumers
  ↓
Operational Impact
```

Keep tracing until there are **no more consumers**.  
**Do not stop** after finding where data is saved.

---

## Discovery requirements

For every feature, discover:

### 1. Entry points

REST · Webhook · Cron · Scheduler · Consumer · Listener · Queue · Manual trigger · Admin action · Bulk sync · Migration

### 2. Write paths

- Which class owns writes?
- Is there exactly one write path?
- Are writes duplicated?

### 3. Read paths

Who reads? Dashboards · Analytics · Automation · Search · Exports · API · UI

### 4. Mapping

- Where is mapping duplicated?
- Can mapping drift?
- Are different platforms mapped differently?

### 5. Identifier contract

Document:

- Internal ID
- External ID
- Business ID
- Natural key
- Lookup key
- Dedup key
- Correlation key

### 6. Lifecycle

State machine · Transitions · Scheduler · Expiration · Recovery · Retries · Deletion

### 7. Platform parity

Compare every platform:

Shopify · WooCommerce · WordPress · REST · Bulk Sync · Webhook · Import · Migration

Highlight **every** inconsistency.

---

## Search for (architecture smells)

- Hidden coupling
- Duplicated business logic
- Silent failures
- Null paths
- Race conditions
- Async inconsistencies
- Dead code
- Legacy paths
- Commented infrastructure
- Feature flags
- Fallback logic
- Backward compatibility
- Incomplete implementations
- TODOs / FIXMEs
- Magic constants
- Unused abstractions
- Incorrect ownership
- Large classes
- Circular dependencies
- Different code paths implementing the same feature

---

## Downstream discovery

Identify every downstream dependency. If one component changes, document everything affected.

Examples: Search Index (Elasticsearch / OpenSearch) · Analytics · Automation · Notifications · Scheduler · Activity Timeline · Audit · Reporting · Email · Caching · Webhooks · Events · Public APIs

---

## Failure analysis

For every important path document:

| Scenario | Document |
|----------|----------|
| Happy path | Expected behavior with code refs |
| Missing data | What happens; who notices |
| Partial failure | Committed vs rolled back |
| Duplicate events | Dedup behavior |
| Retry | Idempotency and side effects |
| Race condition | Concurrent requests |
| Network failure | Timeouts, partial writes |
| External dependency failure | Degradation mode |
| Missing configuration | Default vs error |
| Recovery behavior | Manual vs automatic |
| Silent skip | Where logs/metrics are absent |
| Dead letter | If any |
| Operational visibility | Dashboards, alerts, support signals |

---

## Security review

Verify from code:

Authentication · Authorization · Webhook verification · HMAC · Bearer tokens · Secret usage · Replay protection · Idempotency · Rate limiting · Tenant isolation · Account isolation · Hardcoded secrets · Trust boundaries

---

## Test review

Determine:

- What is tested
- What is not tested
- Critical missing integration tests
- Critical missing regression tests
- Critical platform parity tests

---

## Knowledge base maintenance (mandatory during audit)

Treat existing architecture KBs as source of truth. While auditing:

- Verify whether the KB is still accurate
- Identify missing knowledge
- Identify outdated sections
- Identify architectural drift
- Identify implementation drift
- Identify newly introduced code paths
- Identify obsolete code paths

**Do not duplicate** existing documentation. Instead:

1. **Extend** existing KBs where appropriate (`ai/kb/`, `docs/`, `service/docs/`, `.cursor/rules/*.mdc`)
2. **Mark** obsolete knowledge (date + replacement pointer)
3. Keep **terminology** consistent across KBs
4. **Cross-reference** related KBs
5. **Recommend KB updates** whenever architecture changes

**KB workflow:** `ai/docs/kb-updater-workbook.md` · `ai/prompts/kb-extraction.md` · `ai/prompts/kb-update-suggestion.md` · `ai/prompts/kb-verification.md`

**Register new topic KBs** in `ai/kb/README.md` and `docs/kb/INVENTORY.md`.

---

## Document output — required content by file

### Architecture KB (`<FEATURE>_ARCHITECTURE.md`)

| Order | Section |
|-------|---------|
| 1 | Overview & design invariants (diagram) |
| 2 | **Architecture Invariants** (I-1… table; hold/fail per invariant) |
| 3 | Data model |
| 4 | Lifecycle & state machines |
| 5 | Entry points |
| 6 | Identifier contract |
| 7 | Downstream contracts |
| 8 | Key files & appendix |

### Audit findings (`<FEATURE>_AUDIT.md`)

| Order | Section |
|-------|---------|
| 1 | Executive summary + risks register (✅/⚠/class/confidence) |
| 2 | Scope |
| 3 | **Non-goals** |
| 4 | **Assumptions** |
| 5 | Platform comparison & gaps |
| 6 | Failure catalog |
| 7 | Security · Operational · Performance |
| 8 | Architecture smells · Technical debt · KB drift |
| 9 | Recommendations (💡; reference decision criteria in ADR) |
| 10 | Migration strategy |
| 11 | Validation matrix |
| 12 | Review checklist |

### ADR (`<FEATURE>_ADR.md`)

| Order | Section |
|-------|---------|
| 1 | **Decision criteria** (weighted criteria for this feature) |
| 2 | Individual ADRs (context → options → decision → consequences) |
| 3 | **Decision log** (summary table: ID, status, reason, date, owner) |

### Full trace checklist (all three files combined)

Use when auditing — maps to original end-to-end trace:

Executive Summary → Architecture Overview → **Invariants** → Entry Points → Lifecycle → Data Model → Identifier Contract → Field Mapping → Platform Comparison → Sequence Diagrams → State Machines → Downstream → Failure Catalog → Security → Operational → Performance → Smells → Debt → **Non-goals** → **Assumptions** → **Decision Criteria** → Recommendations → Migration → Validation → Review Checklist → Appendix → ADRs → **Decision Log**

**Placement:**

- **Architecture KB** → `service/docs/<FEATURE>_ARCHITECTURE.md` (✅ facts, long-lived)
- **Audit findings** → `service/docs/<FEATURE>_AUDIT.md` (✅ + ⚠)
- **ADR** → `service/docs/<FEATURE>_ADR.md` (💡 decisions)
- **Compressed findings for AI** → `ai/kb/<topic>.md` with pointers to all three
- **Phase / track audits** (`ai/kb/<track>-audit.md`) — historical; new full audits use document suite

---

## Important rules

| Rule | Detail |
|------|--------|
| **No speculation** | Every finding must reference actual code (class, method, file path). |
| **Separate facts vs recommendations** | Use ✅ Verified / ⚠ Impact / 💡 Recommendation labels; confidence High/Medium/Low on every finding. |
| **Insufficient evidence** | Explicitly state **“Not verified.”** |
| **Understand before refactor** | Do not recommend consolidation until every implementation is documented. |
| **Multiple implementations** | Document **every** implementation path before recommending merge. |
| **Explain impact** | For each problem: **WHY** it matters, **WHAT** production behavior it causes, **WHO** consumes it, **HOW** it should ideally behave. |
| **Principal Engineer stance** | Review a critical production system — not a linter. |

---

## Pre-audit KB inventory (start here)

Before reading code, load and note what claims to be true:

| Area | Starting KB |
|------|-------------|
| Email Builder | `docs/EMAIL_BUILDER_*.md`, `ai/kb/architecture.md`, `.cursor/rules/email-builder-typescript-architecture-kb.mdc` |
| Popup / embed | `ai/kb/popup-rendering-css-architecture.md`, `ai/kb/popup-builder-rendering-pipeline.md` |
| Automations | `docs/automation-behavior.md`, `arch/automation-workflow.md` |
| Integrations / commerce | `service/docs/integration-connection-config-kb.md`, `service/docs/INTEGRATION_LIFECYCLE.md` |
| Elasticsearch sync | `ai/kb/elasticsearch-mongo-indexing.md`, `.cursor/rules/backend-implementation.mdc` |
| Entitlements | `ai/kb/entitlements-and-subscription.md` |
| Full catalog | `docs/kb/INVENTORY.md`, `ai/kb/README.md` |

During the audit, record **KB drift** in section 17 (Technical Debt) and in KB update recommendations.

---

## Review checklist (sign-off)

Use this before publishing the audit:

- [ ] All entry points enumerated with file/class references
- [ ] Write path ownership confirmed; duplicate paths documented
- [ ] Read paths traced to final consumer (not stopped at DB)
- [ ] Identifier contract table complete per platform
- [ ] Platform parity table includes every integration surface
- [ ] Async / scheduler / change-stream paths traced
- [ ] Automation and analytics impact stated or marked *Not verified*
- [ ] Failure catalog covers silent skip and retry behavior
- [ ] Security controls verified in code (not assumed from docs)
- [ ] Test gaps listed with priority
- [ ] Existing KB checked; drift and update recommendations included
- [ ] Facts and recommendations clearly separated (✅ / ⚠ / 💡) with confidence levels
- [ ] Product correctness vs engineering debt tagged in roadmap
- [ ] **Architecture invariants** declared; violations mapped to findings
- [ ] **Non-goals** and **Assumptions** documented (assumptions not presented as ✅)
- [ ] **Decision criteria** stated in ADR doc before recommendations
- [ ] **Decision log** table complete (status, date, owner)
- [ ] Document suite split applied when output is reference-grade (>800 lines)
- [ ] No recommendation without documented current behavior first
