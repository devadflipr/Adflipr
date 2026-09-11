# KB — Knowledge Base

Teach the AI how the Email Builder works. Use these with prompts and verification checklists.

**Product/runtime behavior specs:** [`docs/BEHAVIOR_INDEX.md`](../../docs/BEHAVIOR_INDEX.md)

**Dates, times, timezones (platform):** [`docs/datetime-timezone-behavior.md`](../../docs/datetime-timezone-behavior.md)

**Full file catalog (module, role, duplicate clusters):** [`docs/kb/INVENTORY.md`](../../docs/kb/INVENTORY.md)

**KB updater (human approves edits):** [`ai/docs/kb-updater-workbook.md`](../docs/kb-updater-workbook.md) · prompts `ai/prompts/kb-*.md`

| File | Purpose |
|------|---------|
| **architecture.md** | Pipeline, principles, event ownership, runtime. |
| **guardrails.md** | What not to do (no DOM mutation, no hydrate from traits, no editor.__*, etc.). |
| **patterns.md** | Reusable patterns (attribute-scoped hydration, trait cleanup, style guards, runtime guards). |
| **anti-patterns.md** | Symptom → bad approach → fix (complements guardrails). |
| **decisions.md** | ADR-lite log (why `ai/` vs rules, docs vs kb, trait/hydration split). |
| **growave-integration.md** | Growave partner integration v1: connect, webhooks, enrichment, workflow events (locked decisions). |
| **merchant-api-keys.md** | Account-scoped merchant API keys: fixed `api-key → Permission` config, contacts+write v1, MCP (ADR-015). |
| **growave-klaviyo-omnisend-parity-gaps.md** | Klaviyo · Omnisend · Adflipr side-by-side trigger matrix + parity gap inventory. |
| **contact-repository-save-vs-update.md** | `ContactRepositoryWrapper.save` = create-only; `update` for mutations on existing contacts. |
| **frontend-css-colocation.md** | Prefer CSS Modules / feature SCSS over `global.scss` for component UI (`adflipr-app`). |
| **integration-type.md** | `IntegrationType` = commerce store origin only (`SHOPIFY` / `WOOCOMMERCE`); partners use `OTHER` + `integrationName`. |
| **integration-connection-family.md** | COMMERCE / ESP / PARTNER selection rule + connection vs import-policy split sketch (ADR-013). |
| **razorpay-webhook-idempotency.md** | Razorpay webhook event-occurrence idempotency (locked decisions). |
| **gateway-selection.md** | Razorpay vs Shopify billing gateway: first purchase by signup source, later by payment history. |
| **block-anatomy.md** | Block = model + view + traits + events; who owns what. |
| **email-templates-no-null.md** | Email templates and sending: no literal "null" in content; backend guard and sanitization. |
| **product-block-kb.md** | Product block: three rules (no DOM, style via hydration only, traits → attributes), audit summary, file roles, refactor direction. |
| **popup-builder-structure-dnd.md** | Popup form builder: global structure drag & drop (rules, mutations, react-dnd, 3-file layout). |
| **popup-html-serialization.md** | Popup export serializer: `htmlContent` on save, sanitization, thumbnail/embed contract. |
| **popup-css-lifecycle-audit.md** | **As-is** popup CSS lifecycle: generation → storage → hoisting → cleanup; inline mix (§11); cascade audit PCL-008 (§12). |
| **popup-rendering-css-architecture.md** | **Target** storefront CSS architecture: `htmlByStep` + `renderCss`, invariants, migration phases; mandatory review KB for serializer/embed. |
| **popup-render-artifact-contract-phase1a.md** | **Phase 1A** render artifact contract decision (types, versioning, storage, backward compat). |
| **popup-column-presentation-css-phase4b-report.md** | **Phase 4B** column presentation → `renderCss` (forward-only). |
| **popup-column-presentation-css-phase4d-report.md** | **Phase 4D** forward-only cleanup implementation report. |
| **popup-css-phase4e-acceptance-report.md** | **Phase 4E** acceptance verification (Phases 1–4 closed). |
| **popup-block-css-phase5a-ownership-audit.md** | **Phase 5A** block DOM ownership audit (audit only — no migration). |
| **popup-block-ownership-contract-phase5b-audit.md** | **Phase 5B** block ownership contract architecture audit (decision only — no migration). |
| **popup-button-presentation-css-phase5c-report.md** | **Phase 5C** Button presentation → `renderCss` (first block-family migration). |
| **popup-button-presentation-css-phase5c-acceptance-audit.md** | **Phase 5C acceptance** — Principal Engineer pattern repeatability audit. |
| **popup-text-presentation-css-phase5d-report.md** | **Phase 5D** Text presentation → `renderCss` (second block-family migration). |
| **popup-positioned-decorative-css-phase5e-report.md** | **Phase 5E** Cross + floating-image positioned decorative → `renderCss`. |
| **popup-positioned-decorative-css-phase5e-acceptance-audit.md** | **Phase 5E acceptance** — Principal Engineer verification before Teaser. |
| **popup-teaser-presentation-css-next-phase-report.md** | **Teaser + Phase 0 closure** — style-only teaser chip → `renderCss`; baseline fully green. |
| **popup-standard-field-css-phase6b-report.md** | **Phase 6B** Standard Field (`text-input`, `email-input`) → `renderCss`. |
| **popup-textarea-css-phase6c-report.md** | **Phase 6C** Textarea → `renderCss` (dedicated compiler; height on native field). |
| **popup-dropdown-css-phase6d-report.md** | **Phase 6D** Dropdown → `renderCss` (dedicated compiler; outline height pattern). |
| **popup-global-trait-presentation-architecture-audit.md** | **Global trait audit** — shared inline-first engine vs 13 families; G1 gate → [`popup-export-css-production-build-fix-phase-g1-report.md`](./popup-export-css-production-build-fix-phase-g1-report.md). |
| **popup-export-css-production-build-fix-phase-g1-report.md** | **Phase G1** — production `.export.css` raw-source build contract fix (craco oneOf + CI gate). |
| **popup-production-css-rte-ownership-audit.md** | **Production `.export.css` + RTE/trait split** audit (blockers; audit only). **Fix:** [`popup-export-css-production-build-fix-phase-g1-report.md`](./popup-export-css-production-build-fix-phase-g1-report.md). |
| **popup-rte-command-ownership-phase-g2a-audit.md** | **Phase G2A** RTE command ownership matrix + configurable registry design (audit only). |
| **popup-saved-artifact-storefront-css-parity-phase-g2e-audit.md** | **Phase G2E** save → preview → thumbnail → storefront artifact/CSS parity audit (read-only). |
| **popup-remaining-block-css-phase6a-audit.md** | **Phase 6A** remaining block-family DOM ownership audit. |
| **popup-columns-container-css-phase3c-report.md** | **Phase 3C** columns container + slot mechanics → `renderCss`. |
| **popup-responsive-css-phase0-baseline.md** | Responsive CSS migration scoreboard (red/migrated/controls). |
| **popup-builder-rendering-pipeline.md** | Current builder → serializer → embed pipeline (links to target architecture). |
| **wordpress-popup-rendering-audit.md** | WordPress storefront popup pipeline: WP loader → embed JS → API → innerHTML; visibility, triggers, Top 10 preview vs live gaps. |
| **popup-embed-production-bugs.md** | **Production bug registry** (PEB-###): reliability, lifecycle, integrations, analytics; repro steps, fixes, regression tests, priority. |
| **popup-runtime-v2-design.md** | **Runtime V2 design** (merchant embed only): state machine, modules, lifecycle, a11y, queue, migration roadmap. |
| **popup-block-registry.md** | **Popup block registry contract & plan**: builder / serializer / embed hooks, phased roadmap, new-block checklist. |
| **popup-block-registry-phases-0-2.md** | **Phase 0–2 execution plan** (runtime registry, serializer map — **done**). |
| **popup-block-registry-phase-3-plan.md** | **Phase 3 builder plan** (modularization waves, parity QA, risks — planning). |
| **popup-gamification-spinner.md** | **Gamification spinner** — button-only spin, popup-wide singletons, protected system block. Suite: `docs/POPUP_GAMIFICATION_SPINNER_*.md`. |
| **spin-to-win-brd.md** | Spin-to-win BRD (outcomes, spin API, thank-you coupon). |

### Infrastructure

| File | Purpose |
|------|---------|
| **infrastructure-hosts.md** | SSH host aliases (**fe-prod**, **app-prod**, **fe-dev**, **app-dev**) and IPs; full droplet lists in `docs/INFRA_ADFLIPR_*.md`. |

### Backend (service)

| File | Purpose |
|------|---------|
| **entitlements-and-subscription.md** | Workspace **entitlements** (`GET /api/v1/entitlements`, `POST /api/v1/feature/check`): plan from **`Subscription`**, usage from **`UsageService`** (live **`EntitlementUsage`**, no usage collection), static config, dev seeding, plan-type enums, **Shopify connect** validation notes. |
| **plan-pricing-resolution.md** | Plan catalog layers (global / partner / account): display vs **post-payment** resolution; never silent zero email quota. Plan + code audit: `service/docs/PLAN_PRICING_POST_PAYMENT_RESOLUTION.md`. |
| **razorpay-webhook-idempotency.md** | **Locked decisions** for Razorpay webhook idempotency (event-occurrence keys, FAILED/stale retry, ordering vs dedupe). Service pointer: `service/docs/RAZORPAY_WEBHOOK_IDEMPOTENCY.md`. ADR-010. |
| **elasticsearch-mongo-indexing.md** | Elasticsearch **indexing** is driven by MongoDB **change streams** → `MongoEventConsumer` → `ElasticsearchService`; not ad-hoc per repository. |
| **commerce-transaction-integration-audit.md** | *(pointer)* Commerce doc suite: as-is, audit, **target architecture**, migration, TA ADRs. **Phase 0 shipped** (Sprint S1). |

**Service docs (integrations):** [`service/docs/integration-connection-config-kb.md`](../../service/docs/integration-connection-config-kb.md) — unified connection config, `(accountId, provider)` cardinality, shared syncTrigger and conflict policy (contacts only; transactions deferred). ADR: `decisions.md` ADR-004.

**Audit methodology (general auditor):** [`ai/verification/principal-engineer-architecture-audit.md`](../verification/principal-engineer-architecture-audit.md). **Commerce suite:** as-is [`TRANSACTION_INGESTION_ARCHITECTURE.md`](../../service/docs/TRANSACTION_INGESTION_ARCHITECTURE.md) + audit + **target** [`TRANSACTION_TARGET_ARCHITECTURE.md`](../../service/docs/TRANSACTION_TARGET_ARCHITECTURE.md), [`TRANSACTION_MIGRATION_PLAN.md`](../../service/docs/TRANSACTION_MIGRATION_PLAN.md), [`TRANSACTION_ARCHITECTURE_ADR.md`](../../service/docs/TRANSACTION_ARCHITECTURE_ADR.md).

**Canonical details** live in:

- `docs/EMAIL_BUILDER_ARCHITECTURE.md`
- `docs/EMAIL_BUILDER_TRAITS.md` (traits: contract, panel/model, `registerTraits`, anti-patterns)
- `.cursor/rules/email-builder-typescript-architecture-kb.mdc`
- `.cursor/rules/grapesjs-lifecycle-events-kb.mdc`
- `.cursor/rules/traits-contract-kb.mdc`
- `.cursor/rules/editor-runtime-store-kb.mdc`

Use this KB for: refactors, new blocks, trait changes, and any change that touches hydration or editor state. For backend Elasticsearch **sync** behavior, use **elasticsearch-mongo-indexing.md** and `.cursor/rules/backend-implementation.mdc` (Elasticsearch section).
