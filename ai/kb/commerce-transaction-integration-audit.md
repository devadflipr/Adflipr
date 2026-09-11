# Commerce transaction integration — KB pointer

**Document suite (canonical):**

| Doc | Role |
|-----|------|
| [`TRANSACTION_TARGET_ARCHITECTURE.md`](../../service/docs/TRANSACTION_TARGET_ARCHITECTURE.md) | 🎯 **Target** canonical platform design |
| [`TRANSACTION_MIGRATION_PLAN.md`](../../service/docs/TRANSACTION_MIGRATION_PLAN.md) | Phased migration + KB updates |
| [`TRANSACTION_ARCHITECTURE_ADR.md`](../../service/docs/TRANSACTION_ARCHITECTURE_ADR.md) | Target ADRs (TA-001…) + decision log |
| [`TRANSACTION_ARCHITECTURE_CHALLENGE_REPORT.md`](../../service/docs/TRANSACTION_ARCHITECTURE_CHALLENGE_REPORT.md) | 🔍 Independent challenge — **APPROVE WITH CHANGES** |
| [`TRANSACTION_INGESTION_ARCHITECTURE.md`](../../service/docs/TRANSACTION_INGESTION_ARCHITECTURE.md) | ✅ Current as-is reference |
| [`COMMERCE_TRANSACTION_INTEGRATION_AUDIT.md`](../../service/docs/COMMERCE_TRANSACTION_INTEGRATION_AUDIT.md) | ✅ + ⚠ Audit findings |

**Implementation pack (execution):**

| Doc | Role |
|-----|------|
| [`IMPLEMENTATION_READINESS_REVIEW.md`](../../service/docs/IMPLEMENTATION_READINESS_REVIEW.md) | 🚦 Execution gate — start Phase 0 |
| [`IMPLEMENTATION_DEPENDENCY_GRAPH.md`](../../service/docs/IMPLEMENTATION_DEPENDENCY_GRAPH.md) | Phase blocking graph |
| [`REFACTORING_PLAN.md`](../../service/docs/REFACTORING_PLAN.md) | Class-by-class roadmap |
| [`FEATURE_FLAG_STRATEGY.md`](../../service/docs/FEATURE_FLAG_STRATEGY.md) | Temporary flags only |
| [`TEST_MIGRATION_PLAN.md`](../../service/docs/TEST_MIGRATION_PLAN.md) | Tests per phase |
| [`OPERATIONAL_ROLLOUT_PLAN.md`](../../service/docs/OPERATIONAL_ROLLOUT_PLAN.md) | Metrics, runbooks, comms |
| [`SPRINT_EXECUTION_PLAN.md`](../../service/docs/SPRINT_EXECUTION_PLAN.md) | Releasable sprints |
| [`IMPLEMENTATION_CHECKLIST.md`](../../service/docs/IMPLEMENTATION_CHECKLIST.md) | Master checklist |
| [`TRANSACTION_INGEST_DASHBOARD.md`](../../service/docs/TRANSACTION_INGEST_DASHBOARD.md) | Sprint S2 — dashboard + alerts |
| [`runbooks/transaction-migration/`](../../service/docs/runbooks/transaction-migration/) | Replay + baseline capture |

**Execution authority:** Challenge report amendments override TARGET/MIGRATION where they conflict.

**Implementation status (2026-07-11):**

| Phase | Sprint | Status |
|-------|--------|--------|
| **0 — Guardrails** | S1 | **Shipped** — ingest metrics, structured logs, null-contact guard |
| 0 — Soak | S2 | **In progress** — prometheus export, dashboard spec, runbooks |
| 1a — Contact + criteria + ORDER_CREATED | S3–S4 | Not started |
| 1b — Scoped dedup | S5 | Not started |

**Phase 0 code (✅ Verified):**

| Artifact | Path |
|----------|------|
| Metrics | `service/.../transaction/TransactionIngestMetrics.java` |
| Result tags | `TransactionIngestResult` (`SUCCESS`, `SKIPPED_VALIDATION`, `ERROR`, `DUPLICATE`) |
| Topic tags | `TransactionIngestTopic` |
| Instrumented handlers | `TransactionIntegrationService` — all four webhook handlers |
| Null guard | `createTransaction()` returns `null` when `contact == null`; Woo `handleWooCommerceOrderPaid` early skip |

**Invariant delta:** I-6 **Partial hold** — webhook ingest failures observable; change-stream `contactId` gate and bulk paths unchanged until Phase 1a/S2.

**ADR:** [ADR-009](../decisions.md#adr-009-transaction-platform-phase-0-ingest-observability) · [ADR-TI-008](../../service/docs/TRANSACTION_INGESTION_ADR.md#adr-ti-008-phase-0-ingest-observability) · [ADR-TA-011](../../service/docs/TRANSACTION_ARCHITECTURE_ADR.md#adr-ta-011-phase-0-ingest-observability)

**Methodology:** [`principal-engineer-architecture-audit.md`](../verification/principal-engineer-architecture-audit.md)

**Invariants to re-check:** I-1 single write path · I-7 analytics predicate · see architecture doc.

**Related:** `integration-connection-config-kb.md`, `elasticsearch-mongo-indexing.md`
