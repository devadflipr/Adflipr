# Prompt template — General architecture audit (canonical)

**Playbook:** [`ai/verification/principal-engineer-architecture-audit.md`](../verification/principal-engineer-architecture-audit.md)

---

## Invocation

Principal Engineer audit of **{SUBJECT}** — engineering **reference**, not code-style review. Read all relevant code first.

---

## Subject

| Field | Value |
|-------|--------|
| **Feature** | {SUBJECT} |
| **Scope** | {IN_SCOPE} |
| **Outputs** | `{FEATURE}_ARCHITECTURE.md`, `{FEATURE}_AUDIT.md`, `{FEATURE}_ADR.md` |

---

## Document suite

### 1. Architecture KB — ✅ facts

- Overview + diagram
- **Architecture invariants** (I-1… table: hold/violate)
- Data model, lifecycle, state machines
- Entry points, identifier contract, downstream contracts

### 2. Audit findings — ✅ + ⚠

- Executive summary + risks register (class + confidence)
- Scope
- **Non-goals** (does not evaluate / intentionally excludes)
- **Assumptions** (confidence, evidence, if wrong)
- Gaps, failure catalog, security, ops, performance, debt, KB drift
- Recommendations (reference ADR decision criteria)

### 3. ADR — 💡 decisions

- **Decision criteria** (weighted: correctness, migration cost, …)
- Individual ADRs (context → options → decision → consequences)
- **Decision log** (ID, status, reason, date, owner)

**Statuses:** Accepted · Proposed · Superseded · Rejected · Deferred

---

## Evidence

| Label | Use |
|-------|-----|
| ✅ Verified | Code-cited behavior |
| ⚠ Impact | Production effect |
| 💡 Recommendation | After ✅ + ⚠ |

| Confidence | When |
|------------|------|
| High / Medium / Low | Per playbook |

**Reference:** `service/docs/TRANSACTION_INGESTION_*`

---

## Constraints

- Assumptions ≠ verified facts.
- Invariants checked on every future review.
- Extend KBs; cross-reference; do not duplicate.
