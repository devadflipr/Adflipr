# Architectural decisions (ADR-lite) — Adflipr AI KB

**Purpose:** Short, durable decisions that explain *why* the repo is shaped this way. Link to deep docs; do not duplicate full architecture here.

**Format:** `### ADR-###: Title` — Context → Decision → Consequences → References.

**KB workflow:** New decisions from a PR discussion → extract with `ai/prompts/kb-extraction.md`, place with `kb-update-suggestion.md`, verify with `kb-verification.md`. See `ai/docs/kb-updater-workbook.md`.

---

### ADR-001: `ai/` is canonical for process KB

- **Context:** Duplication between `.cursor/rules` and scattered markdown confused agents and humans.
- **Decision:** `ai/` holds skills, prompts, verification, and compressed KB; `.cursor/rules` stay **thin** and pointer-heavy (`ai-folder-canonical.mdc`).
- **Consequences:** New playbooks go under `ai/skills/` or `ai/kb/` first; rules gain a pointer, not a copy-paste of the full playbook.
- **References:** `.cursor/rules/ai-folder-canonical.mdc`, `ai/README.md`.

---

### ADR-002: Long-form Email Builder docs live under `docs/`

- **Context:** Multiple “authoritative” styling/trait docs contradicted each other.
- **Decision:** Canonical narrative for pipeline, traits, hydration, preview, clone/drag is `docs/EMAIL_BUILDER_*.md`; `ai/kb/*.md` summarizes for AI tasks.
- **Consequences:** Refactors that change contracts must update `docs/` first, then align `ai/kb` + `.mdc` pointers.
- **References:** `docs/KB_RATIONALIZATION_SUMMARY.md`, `docs/kb/INVENTORY.md`.

---

### ADR-003: Traits are attribute writers; hydration is projection

- **Context:** Traits calling styles or hydrate directly caused double updates and broken undo boundaries.
- **Decision:** Traits write `data-*` via `addAttributes` only; hydration reads attributes and applies styles; no `editor.__*` for runtime.
- **Consequences:** New traits and blocks must register cleanup for React/listeners; device-paired attrs follow `responsive-attribute-sync-kb.mdc`.
- **References:** `docs/EMAIL_BUILDER_TRAITS.md`, `.cursor/rules/traits-contract-kb.mdc`, `ai/kb/guardrails.md`.

---

### ADR-004: Unified integration connection config (contacts)

- **Context:** Commerce (`Session` + hardcoded upsert rules) and ESP (`esp_config` + `ConflictResolutionService`) evolved separately; product wants one configurable model per `(accountId, provider)`.
- **Decision:** One active connection per **provider** (not duplicate same provider); credentials in `Session`/encrypted keys; **policy** (`syncTrigger`, `onConnectSync`, `syncScope`, field-level conflict authority) on `Session` (commerce) and `esp_config` (ESP) — **option A**, no third config collection; shared inbound contact sync + `external_mapping`. Transactions stay out of this model until a separate KB.
- **Consequences:** Commerce contact sync uses `CommerceSessionConfigService` + `SessionConflictPolicy`; APIs move toward list-per-account (`GET /integrations/connections`); optional phase 5 may merge `esp_config` into a single collection.
- **References:** `service/docs/integration-connection-config-kb.md`, `service/docs/INTEGRATION_LIFECYCLE.md`, `service/docs/mailerlite-integration-architecture.md`.

---

### ADR-005: Sticky banner header clearance — no manual pixel offsets as primary UX

- **Context:** Top sticky banners use `position: fixed` and overlay the storefront. A proposed fix (PEB-044) added Theme → **Offset → Top** so merchants enter header height in px. Merchants typically do not know desktop/mobile/sticky nav/announcement bar heights; fixed preview defaults (56px / 80px) were wrong for many themes (e.g. AdCarts). Overlap remained while adding configuration friction.
- **Decision:**
  - **Do not** treat manual `formStyle.positionOffset` (top/bottom px) as the **primary** solution for sticky banner clearing theme headers.
  - **Do not** add banner offset fields in builder UI, preview default insets, or export `positionOffset` for `STICKY_BANNER` in embed API.
  - **Side message (flyout)** may keep `positionOffset` — edge inset from panel edges is a different, acceptable use case.
  - **Future primary fix** must be automatic (e.g. detect fixed/sticky header height on embed init, theme integration hooks, or preview layout that reserves header band without cross-origin measurement). Pick in a follow-up ADR when implementing.
  - Low-level `buildBannerHostOffsetCss` in embed may remain for internal/tests only; not merchant-facing.
- **Consequences:** PEB-044 remains open until an automatic approach ships. Preview/live banner may still overlap headers until then. Agents and PRs must not reintroduce “set Offset → Top” as the default recommendation for banners.
- **References:** `ai/kb/popup-sticky-banner-header-clearance.md`, `ai/kb/popup-embed-production-bugs.md` (PEB-044), `adflipr-app/src/layouts/PopupBuilder/embed/bannerLayout.ts`.

---

### ADR-006: General architecture audit playbook (canonical general auditor)

- **Context:** Feature audits (commerce, automations, integrations, popup phases) were ad hoc with inconsistent section order and depth. Email Builder `architecture-checklist.md` is a post-refactor gate, not a production audit. Teams need **one** audit standard.
- **Decision:** The **general auditor** — canonical methodology in **`ai/verification/principal-engineer-architecture-audit.md`**; invocation **`ai/prompts/principal-engineer-audit-template.md`**; Cursor **`.cursor/rules/principal-engineer-audit.mdc`**. **Every** audit request must use this playbook (22 sections, full trace chain, KB drift check). Long-form outputs → `service/docs/` or `docs/`; compressed topic KB → `ai/kb/`.
- **Consequences:** No custom audit formats. Agents and humans produce comparable evidence-based reports. Audits extend existing KBs rather than duplicate (`ai/docs/kb-updater-workbook.md`). `kb-first-socratic.mdc` points here for any “audit” request.
- **References:** `ai/verification/principal-engineer-architecture-audit.md`, `ai/prompts/principal-engineer-audit-template.md`, `.cursor/rules/principal-engineer-audit.mdc`, commerce doc suite (`TRANSACTION_INGESTION_*`).

---

### ADR-007: Audit document suite (architecture + findings + ADR)

- **Context:** Reference-grade audits (commerce ingestion) exceeded ~1200 lines; mixed ✅ facts, ⚠ impact, and 💡 recommendations in one file — hard to maintain and review.
- **Decision:** Large subsystem audits split into three linked docs: `<FEATURE>_ARCHITECTURE.md` (✅), `<FEATURE>_AUDIT.md` (✅+⚠), `<FEATURE>_ADR.md` (💡). General auditor playbook requires evidence taxonomy (✅/⚠/💡), confidence levels (High/Medium/Low), and product-vs-debt classification. Section 23 = ADRs.
- **Consequences:** Commerce reference implementation uses full suite including invariants, non-goals, assumptions, decision criteria, and decision log.
- **References:** `ai/verification/principal-engineer-architecture-audit.md`, `service/docs/TRANSACTION_INGESTION_*`.

---

### ADR-008: Audit refinements — invariants, non-goals, assumptions, decision log

- **Context:** Reference audits needed explicit invariants for regression review, non-goals to limit scope, assumptions for infra not in repo, decision criteria for transparent ADRs, and a decision log for evolution over time.
- **Decision:** General auditor playbook requires: **Architecture Invariants** (architecture KB), **Non-goals** + **Assumptions** (audit findings), **Decision criteria** + **Decision log** with statuses Accepted/Proposed/Superseded/Rejected/Deferred and Owner (ADR doc).
- **Consequences:** All future subsystem audits include these sections; commerce suite updated as reference.
- **References:** `ai/verification/principal-engineer-architecture-audit.md`, `service/docs/TRANSACTION_INGESTION_ADR.md`.

---

### ADR-009: Transaction platform Phase 0 ingest observability

- **Context:** Commerce transaction audit (R-06, R-07, I-6) proved null-contact Woo order path NPE'd at `createTransaction`; failures were silent (no metrics). Implementation pack approved Phase 0 before adapter work ([`IMPLEMENTATION_READINESS_REVIEW.md`](../../service/docs/IMPLEMENTATION_READINESS_REVIEW.md)).
- **Decision:**
  - Add **Micrometer** ingest metrics via thin `TransactionIngestMetrics` component (`transaction.ingest.total`, `transaction.ingest.duration`) with tags `platform`, `topic`, `result`.
  - Instrument **all four** webhook handlers in `TransactionIntegrationService` with structured exit logs (`ingestResult`, `durationMs`).
  - **Null-contact guard:** `createTransaction()` returns `null` (no NPE); `handleWooCommerceOrderPaid` skips with `SKIPPED_VALIDATION` when `syncWooCommerceContactsFromOrders` returns `null`.
  - **Do not** add feature flags, adapters, or schema changes in Phase 0.
  - **Execution model:** Evolve `TransactionIntegrationService` → `ingest()` per challenge report — **not** separate `TransactionWritePipeline` class (see TA-001 amendment).
- **Consequences:** Woo orders with `contactSyncEnabled=false` and no existing contact skip cleanly (fewer 500s). Bulk ingest and `MongoEventConsumer` `contactId` gate unchanged until Phase 1a. Sprint S2 adds prometheus export on localhost management port, dashboard spec, and replay/baseline runbooks.
- **References:** `service/docs/TRANSACTION_INGESTION_ADR.md` (ADR-TI-008), `service/docs/TRANSACTION_ARCHITECTURE_ADR.md` (ADR-TA-011), `service/docs/SPRINT_EXECUTION_PLAN.md` (S1), `service/docs/TRANSACTION_ARCHITECTURE_CHALLENGE_REPORT.md`.

---

### ADR-010: Razorpay webhook idempotency (event-occurrence)

- **Context:** Razorpay retries deliveries. As-is ledger uses keys like `razorpay:{event}:{subscriptionId}`, which over-deduplicates recurring `subscription.charged`, and skips **any** existing row (including `FAILED`), blocking retry. Overwrite guards treated new Mongo webhook ids as “newer” business events.
- **Decision:**
  - Webhooks **must** be idempotent (event-occurrence model, not subscription-forever).
  - Dedupe key: composite `razorpay:{event}:{immutableResourceId}` (invoice/payment id preferred; else `subscriptionId+created_at`). Razorpay event-id primary deferred until ingress captures it. **No versioned key prefix (`v2:`)** — change rules only where broken; leave existing ledger rows alone.
  - Skip only `PROCESSED` and fresh `PROCESSING`; retry `FAILED` and stale `PROCESSING` (TTL ~15–30m). `DEAD_LETTER` = manual only.
  - Handlers remain upsert-safe (defense in depth). Ordering is separate: use provider timestamps, not Mongo event-doc novelty.
- **Consequences:** Implementation must change `RazorpayWebhookUtils.buildDedupeKey`, `AbstractWebhookProcessingService` skip/retry, and `shouldWebhookOverwrite`. Unique `dedupeKey` index stays. Decisions locked in KB before code.
- **References:** `ai/kb/razorpay-webhook-idempotency.md`, `service/docs/RAZORPAY_WEBHOOK_IDEMPOTENCY.md`.

---

### ADR-011: Growave partner integration (inbound loyalty / reviews / wishlists)

- **Context:** Merchants use Growave for loyalty, reviews, and wishlists on Shopify. Adflipr needs Klaviyo-style sync: enrich contacts and fire automations. Growave is not an email list ESP; forcing it through `EspImportAdapter` / import policy would be wrong.
- **Decision:**
  - Hub kind **`PARTNER`** (not ESP); credentials live on **`integration_connection_config`** (`family=PARTNER`, `provider=GROWAVE`), secrets via `SecretFieldEncryptionService`. **Do not** add `GROWAVE` to `IntegrationType` (see ADR-012). (History: v1 interim used `Session.growaveMetadata` with `IntegrationType.OTHER`; migrated per ADR-013 — `Session.GrowaveMetadata` is removed.)
  - One active Growave connection per account (unique `(accountId, provider)`); requires active Shopify session. Merchant pastes Growave API Key/API Secret only (no shop field, no OAuth redirect/popup); server derives shop from the account's Shopify session, verifies credentials belong to that shop, then encrypts and stores them.
  - Inbound webhooks only (loyalty + reviews + wishlists topics); occurrence idempotency ledger; enrich **`growave_*` UDFs** and fire **`GROWAVE_*` EventTypes** (Klaviyo-style integration-specific contract — **ADR-014**, supersedes interim `loyalty_*` / provider-agnostic EventType cutover).
  - Referral contact resolve (Klaviyo/Omnisend parity): `referralActivated` → **referred**; `referralCompleted` → **referrer** (`referred`/`referrer` payload nodes, not top-level `customer`).
- **Consequences:** New connect API `/api/v1/growave/*`, webhook `/api/v1/webhooks/growave`, EventType extensions, audience Growave filters + ES mapping for filterable `dynamicField` keys. No outbound award-points in v1. Do not rewrite onto `EspProviderType` as a shortcut.
- **References:** `ai/kb/growave-integration.md`, `ai/kb/growave-klaviyo-model-migration.md`, `ai/kb/integration-type.md`, `ai/kb/integration-connection-family.md`, `service/docs/integration-connection-config-kb.md`, ADR-014.

---

### ADR-014: Growave Klaviyo-style integration-specific events and fields

- **Context:** Adflipr briefly adopted provider-agnostic `loyalty_*` UDFs and generic EventTypes (`POINTS_EARNED`, …) for multi-partner reuse (AE-001 / ADR-011 D5–D6). Product direction is to match Klaviyo/Omnisend: merchants see **Growave-branded metrics and properties**, not a generic Loyalty layer.
- **Decision (locked 2026-07-30):**
  - Contact enrichment keys are **`growave_*`** on `Contact.dynamicField` (write canonical; dual-read `loyalty_*` during migration).
  - Workflow EventTypes for Growave ingress are **`GROWAVE_*`** (e.g. `GROWAVE_EARNED_POINTS`). Legacy enums remain temporarily as **compat aliases** for stored automations.
  - EventSelector / audience filter UX is **Growave**-labeled when the GROWAVE partner is connected (not a multi-provider Loyalty tab).
  - AE-001 and ADR-011 D5/D6 wording for `loyalty_*` + agnostic EventTypes are **superseded**.
- **Consequences:** Mongo + ES migrations; filter catalog and recipes use `growave_*`; webhook mapper emits `GROWAVE_*`; tracking in `ai/kb/growave-klaviyo-model-migration.md`.
- **References:** `ai/kb/growave-klaviyo-model-migration.md`, `ai/kb/growave-integration.md`, `ai/kb/growave-klaviyo-omnisend-parity-gaps.md`.

---

### ADR-015: Merchant account API keys (fixed permission mapping)

- **Context:** Product wants merchants to create API keys (Settings section) for programmatic access and MCP, without inventing a second auth/authorization model. Per-key scopes and per-user custom maps were rejected in favor of one shared mapping for all accounts.
- **Decision (locked 2026-08-01; credential shape updated 2026-08-02):**
  - Keys are **account-scoped** (not user-scoped). Create/list/revoke in Settings → API keys; store **hash of private secret only**, show secret once.
  - Credential shape: **public key + private secret** (both required per request), Klaviyo-style; legacy single `adflipr_…` still accepted.
  - Auth: credentials accepted on the **same API surface** as dashboard JWT. Filter resolves → `accountId` + `AuthContext` (`Role.API_KEY`).
  - Authorization: **one shared config** `api-key → Set<Permission>` plus `allowed-path-prefixes` in YAML. Contact controllers need not carry `@RequiresPermission(CONTACTS)` for JWT/UI safety.
  - Same credentials are intended for MCP and HTTP clients.
- **Consequences:** `account_api_key` collection + `ApiKeyAuthenticationFilter`; Settings UI; do not add per-key permission pickers without a new ADR. Deep design: `ai/kb/merchant-api-keys.md`.
- **References:** `ai/kb/merchant-api-keys.md`, `security.api-key.allowed-path-prefixes` in `role-permission-mapping.yml`.

---

### ADR-012: `IntegrationType` is commerce store origin only

- **Context:** `IntegrationType` on `Session` was briefly extended with partner values (e.g. Growave). That conflates “which store customers come from” with unrelated partner/ESP credentials.
- **Decision:** `IntegrationType` may only be **`SHOPIFY`** or **`WOOCOMMERCE`** (plus legacy `OTHER` for non-commerce partner rows keyed by `integrationName`). Never add partner apps, ESPs, or gateways as new `IntegrationType` enum constants.
- **Consequences:** Partner apps use `OTHER` + `integrationName` (interim) or shared connection layer (ADR-013). ESPs stay on `EspProviderType` / `esp_config` until connection/policy split. `ContactSource` / `IntegrationProvider` may still name partners for provenance/mapping. Agents must follow `ai/kb/integration-type.md` and `.cursor/rules/integration-type.mdc`.
- **References:** `ai/kb/integration-type.md`, `ai/kb/growave-integration.md`, ADR-011 (amended), ADR-013.

---

### ADR-013: Integration family selection + dedicated shared connection collection

- **Context:** Extending “ESP” to mean any third-party connection conflates email-list import with partner enrichment. Growave v1 put typed metadata on `Session`, which the team rejected. An interim exploration reusing `esp_config` (via a `kind` discriminator) was also rejected because it keeps import policy and connection state entangled and mutates the live ESP schema.
- **Decision (locked 2026-07-26):**
  - Select family by **primary job**: COMMERCE / ESP / PARTNER (`ai/kb/integration-connection-family.md` §1). Hub kind must match.
  - Introduce a **dedicated shared collection `integration_connection_config`** holding connection concerns (credentials, status, webhook state, lifecycle, `authoritative`) for **all** ESP + PARTNER connections, with an **`IntegrationFamily { ESP, PARTNER }`** discriminator. One active connection per `(accountId, provider)`.
  - **`EspConfig` is demoted to ESP import policy** (sync scope, importScope, field mappings), linked to the connection by **`connectionId`**. Partner connections have **no** `EspConfig`.
  - PARTNER rows: `family=PARTNER`, provider-neutral `partnerSettings` (`externalAccountKey`, non-secret `providerSettings`), generic credentials + shared webhook state, `partnerDefaults()` authority, **no** `EspImportAdapter`. Import/list/segment/poll machinery gates on `family==ESP`.
  - COMMERCE stays on `Session`. `Session.<Partner>Metadata` is forbidden; `Session.GrowaveMetadata` is removed.
- **Migration:**
  - Phases **1–3 done** (shared connection model/service; Growave on connection row; `Session.GrowaveMetadata` removed).
  - **Phase 3.5 implemented:** `PartnerIntegrationAdapter` registry, generic credential modes, provider-neutral settings/shared webhook state and plug-in lifecycle tests — see `ai/kb/integration-connection-family.md` §5.
  - **Phases 4–5 implemented:** `EspConfig.connectionId` links to the connection row; `EspConnectionLinkService` owns ESP↔connection mapping. ESP connect writes credentials/status/webhook registration **only** to `integration_connection_config` and clears the legacy `EspConfig` copies; reads (`resolveApiKey`, `resolveStatus`, `EspConfigResponse`) prefer the connection row and fall back to legacy `EspConfig` fields until every row is backfilled (`service/scripts/mongo/backfill-esp-integration-connection-config.js`). `EspConfig` is now import policy: sync scope/trigger/frequency, `importScope`, `customFieldMappings`, `authoritative`, `overrideAdfliprManaged`, plus `webhookEnabled` as the policy toggle mirrored onto the connection row.
- **Consequences:** New model + repository (`IntegrationConnectionConfig`), new `IntegrationFamily` enum, connection service; `GrowaveConnectionService` and repo lookups + loyalty-filter gate move off `Session`; KB/rule updated. Agents follow `.cursor/rules/integration-connection-family.mdc`.
- **References:** `ai/kb/integration-connection-family.md`, `service/docs/integration-connection-config-kb.md`, ADR-011, ADR-012.

---

### ADR-016: Domain DNS provider family (registrar / DNS-host adapters)

- **Context:** Sending-domain authentication today is copy-paste DNS (Mailgun records + live DoH verify). Merchants asked to connect GoDaddy, Hostinger, and other registrars so Adflipr can upsert SPF/DKIM/CNAME/DMARC. That job is not COMMERCE, not ESP import, and not PARTNER enrichment (ADR-013 §1).
- **Decision (2026-08-14):**
  - New `IntegrationFamily.DOMAIN_DNS` on the existing `integration_connection_config` collection (credentials, status, lifecycle). Authority is `noneDefaults()` — DNS hosts do not sync contacts.
  - Provider identity is `IntegrationProvider` values with `isDomainDnsProvider()` (GoDaddy, Hostinger, Cloudflare, Namecheap, …). Do **not** add these to `IntegrationType` or `EspProviderType`.
  - Provider behavior implements `DomainDnsProviderAdapter` (validate, list zones/records, upsert). Shared lifecycle stays in `IntegrationConnectionService`. HTTP surface is `/api/v1/domain-providers/*`.
  - Manual-only registrars (Bluehost, SiteGround) stay in the catalog but fail closed — merchants keep copy-paste DNS.
  - Cloudflare is a first-class catalog entry even when it is not the registrar, because nameservers often live there.
- **Consequences:** Skeleton adapters ship as `SKELETON` / `MANUAL_ONLY` (`implementationStatus != READY` rejects connect/apply). Live HTTP clients are follow-up work; Domain Authentication UI and `DomainService` are not wired yet.
- **References:** `ai/kb/domain-dns-provider-integration.md`, `ai/kb/integration-connection-family.md`, ADR-013.
