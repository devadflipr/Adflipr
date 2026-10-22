# Integration connection family — ESP vs partner vs commerce vs channel

**Status:** Decision locked (2026-07-26) — **dedicated shared connection collection `integration_connection_config`** with an `IntegrationFamily` discriminator; `EspConfig` demoted to ESP import policy linked by `connectionId`; `Session` stays commerce-only (ADR-013). CHANNEL family locked 2026-08-16 (ADR-017; lives in `service` / `adflipr-app`, not this module).  
**Implementation:** Phases 1–3, **3.5**, and **4–5 done** (model/lifecycle, Growave migration, partner adapter registry, generic credential modes/settings, ESP link + credential/status migration).
**Audience:** Engineering  
**Related:** [`integration-connection-config-kb.md`](../../service/docs/integration-connection-config-kb.md), [`integration-type.md`](./integration-type.md), [`growave-integration.md`](./growave-integration.md), ADR-013 / ADR-016 in [`decisions.md`](./decisions.md)

---

## 1. Selection rule (mandatory)

When adding an external integration, pick **exactly one** family using the primary job of the connection:

| Family | Primary job | Hub kind | Credential / config home |
|--------|-------------|----------|--------------------------|
| **COMMERCE** | Store origin — customers & orders come from here | `COMMERCE` | `Session` + `IntegrationType` (`SHOPIFY` / `WOOCOMMERCE`) — unchanged |
| **ESP** | Import/sync **email** contacts, lists, segments (includes spreadsheet / lead-form imports such as Google Sheets and Meta Lead Ads) | `ESP` / hub `LEAD_DATA` | `integration_connection_config` (`family=ESP`) for connection + `EspConfig` (import policy, linked by `connectionId`) + `EspImportAdapter` |
| **PARTNER** | Enrich contacts and/or fire automations (loyalty, reviews, wishlists, …) — **not** an email list source | `PARTNER` | `integration_connection_config` (`family=PARTNER`) — connection + `partnerSettings`, **no** `EspConfig`, **no** `EspImportAdapter`, `partnerDefaults()` authority |
| **DOMAIN_DNS** | Push sending-domain DNS records (SPF, DKIM, CNAME, DMARC) at a registrar / DNS host | Settings → Domain authentication (not App Integrations hub) | `integration_connection_config` (`family=DOMAIN_DNS`) + `DomainDnsProviderAdapter`, **`noneDefaults()`** authority, **no** `EspConfig`, **no** partner webhooks |
| **CHANNEL** | Send (and later receive) messages on a non-email channel | `CHANNEL` | `integration_connection_config` (`family=CHANNEL`) + `ChannelSendAdapter`, **`noneDefaults()`** authority, **no** `EspConfig`, **no** `EspImportAdapter` |

`integration_connection_config` is the shared **connection collection** (credentials, status, lifecycle) for ESP, PARTNER, DOMAIN_DNS, and CHANNEL. `EspConfig` holds ESP-only **import policy** and links back via `connectionId`. `Session` is **not** used for ESP, partner, DNS, or channel connections.

### Decision questions (ask in order)

1. **Does Adflipr treat this as the commerce store?** → `COMMERCE` only. Never add partners/ESPs/DNS hosts to `IntegrationType` (ADR-012).
2. **Is the main product surface “import my email list / segments from this provider”?** → `ESP` (`family=ESP`). Implement `EspImportAdapter`; add `EspConfig` import policy.
3. **Is the main product surface enrichment UDFs and/or webhook → workflow events?** → `PARTNER` (`family=PARTNER`). Connection row only + `partnerSettings`; **no** import adapter, **no** `EspConfig`.
4. **Is the main product surface applying sending-domain DNS at a registrar / DNS host?** → `DOMAIN_DNS` (`family=DOMAIN_DNS`). Implement `DomainDnsProviderAdapter`; **no** `EspConfig`, **no** contact authority. See ADR-016 / [`domain-dns-provider-integration.md`](./domain-dns-provider-integration.md).
5. **Is the main product surface sending messages on a non-email channel (SMS, …)?** → `CHANNEL` (`family=CHANNEL`). Implement `ChannelSendAdapter`; **no** `EspConfig`, **no** import adapter, **`noneDefaults()`**. Channel product work lives in `service` / `adflipr-app`.
6. **Reusing connect/status/disconnect?** → Yes, via `integration_connection_config`. Do **not** add `Session.XxxMetadata` for partners, DNS hosts, or channels. Do **not** implement `EspImportAdapter` for partners, DNS hosts, or channels.

### Family behavior lives on the enum

Do **not** stack family constants at call sites (`family == DOMAIN_DNS || family == CHANNEL`). When two families share a policy, add a named predicate on `IntegrationFamily` (e.g. `usesNoneContactAuthority()`) and call that. Identity checks for a single family (`family == CHANNEL` when connecting a channel) are fine.

### Hard rules

| Rule | Why |
|------|-----|
| Connection credentials/status/lifecycle live on `integration_connection_config` (any family) | One shared connection source |
| `EspConfig` exists only for `family=ESP` (import policy), linked by `connectionId` | Keeps import concerns out of the connection row |
| Partner rows **must not** implement `EspImportAdapter` or create an `EspConfig` | Partners have no lists/segments to import |
| Partner rows use `partnerDefaults()` authority, never `espDefaults()` | Prevents overwriting subscription status / profile |
| DOMAIN_DNS rows use `noneDefaults()` authority | DNS hosts do not sync contacts |
| DOMAIN_DNS rows **must not** implement `EspImportAdapter` or `PartnerIntegrationAdapter` | Different job; keep import/partner webhook paths gated |
| CHANNEL rows use `noneDefaults()` authority | Sending must not overwrite email subscribe / profile |
| CHANNEL rows **must not** implement `EspImportAdapter` | Channels send; they do not import lists |
| Shared family policy is a method on `IntegrationFamily`, never `== A || == B` at call sites | One place to update when a new family joins a policy (e.g. `usesNoneContactAuthority()`) |
| Every import/poll path gates on `family==ESP` | Keeps partner, DNS, and channel rows out of import/poll/UI meant for ESPs |
| No partner, DNS, or channel values on `IntegrationType`; no `Session.<Partner>Metadata` | ADR-012; `Session` is commerce origin only |
| One active connection per `(accountId, provider)` | Uniqueness across all families |

---

## 2. `integration_connection_config` — the shared connection collection

A dedicated collection holds the connection (credentials, status, lifecycle) for **all** ESP + PARTNER integrations. ESP import policy stays in `EspConfig`, linked by `connectionId`. Partner-only settings are nested on the connection row.

```
┌─────────────────────────────────────────────────────────────┐
│ IntegrationConnectionConfig  (integration_connection_config)│
│  SHARED (all families):                                     │
│   accountId, provider (IntegrationProvider),                │
│   family: ESP | PARTNER | DOMAIN_DNS | CHANNEL              │
│   connectionName, providerAccountId                         │
│   credentials (encrypted: apiKey | clientId+secret+token)   │
│   status, connectionErrorMessage                            │
│   webhook state (url, enabled, registeredAt, secret)        │
│   rateLimitState, connectedAt / disconnectedAt / deleted    │
│   authoritative (espDefaults | partnerDefaults)             │
│                                                             │
│  partnerSettings (family=PARTNER only):                     │
│   externalAccountKey + providerSettings (non-secret)        │
│   custom-field-only authority                               │
│  credential token expiry + webhook IDs remain shared state  │
└───────────────────────────┬─────────────────────────────────┘
                            │ connectionId (family=ESP only)
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ EspConfig  (collection: esp_config) — IMPORT POLICY ONLY    │
│   syncContacts/Lists/Segments, syncTrigger, frequency,      │
│   importScope, customFieldMappings  → EspImportAdapter      │
└─────────────────────────────────────────────────────────────┘

COMMERCE stays on Session (OAuth store row) — not in either collection.
```

**Migration stance (phases 4–5 done):** ESP connect writes credentials, status and webhook registration **only** to the connection row and clears the legacy `EspConfig` copies. `EspConnectionLinkService` owns the mapping: `resolveApiKey` / `resolveStatus` and `EspConfigResponse` prefer the connection row and fall back to the legacy `EspConfig` fields for rows that predate the migration. Run `service/scripts/mongo/backfill-esp-integration-connection-config.js` to backfill and link existing rows; the fallback can be deleted once no `esp_config` row carries `apiKeyEncrypted`.

### Authority presets (must stay distinct)

| Preset | status | profileFields | customFields | memberships | address |
|--------|--------|---------------|--------------|-------------|---------|
| `espDefaults()` | true | true | true | false | false |
| `commerceDefaults()` | true | true | true | false | true |
| `partnerDefaults()` | **false** | **false** | **true** | false | false |

Partner rows (e.g. Growave enriching `growave_*`) **must** use `partnerDefaults()`.

### `family` gating (mandatory)

Import/poll machinery is ESP-only and **must** filter `family==ESP` (or the presence of a linked `EspConfig`):

- `EspApiService.listConnections()` / `EspController` `/api/v1/esp/{provider}/*`
- Import (`/import`, `EspSyncJob`, `EspImportOrchestrator`) and pollers
- `EspAdapterRegistry.requireAdapter` (never called for partner providers)

Partner connections never enter these paths.

### API direction

- ESP: `/api/v1/esp/*` (unchanged surface; backed by connection row + `EspConfig`)
- PARTNER connect/status/disconnect: provider-specific controller (e.g. `/api/v1/growave/*`) that writes an `integration_connection_config` row with `family=PARTNER`
- DOMAIN_DNS: `/api/v1/domain-providers/*` (catalog, connect, status, disconnect, apply-dns) — `family=DOMAIN_DNS`
- CHANNEL: provider-specific HTTP in `service` / `adflipr-app` — `family=CHANNEL`
- Webhook ingress: `/api/v1/webhooks/{provider}`
- Future: unify under `/api/v1/integrations/{provider}` (connection-config KB phase 4/5)

### Adapter contracts

| Family | Required interface | Not required |
|--------|-------------------|--------------|
| ESP | `EspImportAdapter` (validate, list objects, import) + `EspConfig` policy | Event→automation mapping |
| PARTNER | `PartnerIntegrationAdapter` (validate, webhook subscribe/unsubscribe, webhook→enrichment/event mapping) | `EspImportAdapter` / `EspConfig` / list-segment import |
| DOMAIN_DNS | `DomainDnsProviderAdapter` (validate, list zones/records, upsert sending-domain records) | `EspImportAdapter` / `EspConfig` / partner webhooks / contact authority |
| CHANNEL | `ChannelSendAdapter` (validate, list templates, send, parse webhook) | `EspImportAdapter` / `EspConfig` / contact authority |
| COMMERCE | Existing Session lifecycle + store sync | ESP import adapter |

**Shared vs provider-specific (mandatory for scalability):**

| Layer | Owns | Must not own |
|-------|------|--------------|
| `IntegrationConnectionService` + `integration_connection_config` | Persistence, encryption, lifecycle (connect/status/disconnect/error), operational lookup, `partnerDefaults()`, `(accountId, provider)` uniqueness | Provider OAuth exchange, topic lists, UDF keys, payload→`EventType` mapping |
| Provider adapter + `*ConnectionService` | Auth/validate, webhook registration, enrichment UDFs, event mapping, provider-typed settings | New credential/status fields on `Session`; ESP import paths |
| Controllers | Provider-specific HTTP surface until unified `/api/v1/integrations/{provider}` | Duplicating lifecycle/encryption logic |

---

## 3. Growave on `integration_connection_config`

| Concern | Design |
|---------|--------|
| Hub kind | `PARTNER` |
| Row | `integration_connection_config`, `family=PARTNER`, `provider=GROWAVE`, one active per `(accountId, GROWAVE)` |
| Credentials | shared `credentials` (encrypted Growave API Key / API Secret / access token) |
| Partner settings | `partnerSettings.externalAccountKey=shop`; token expiry is credential state; webhook subscription IDs are shared connection state |
| Authority | `partnerDefaults()` |
| `EspConfig` | none (partner has no import policy) |
| Connect flow | Direct API Key/API Secret form (no shop input, no browser redirect/popup); server derives shop from Shopify session, verifies credentials against that shop, subscribes required webhooks, and writes the connection row — **not** `EspApiService.connect` |
| Status/lifecycle | `status` on the connection row (`CONNECTED` / `DISCONNECTED` / `ERROR`); no `Session` lifecycle for Growave |
| Webhook shop lookup | query `integration_connection_config` `family=PARTNER, provider=GROWAVE, partnerSettings.externalAccountKey=…` |
| Filters | Loyalty section gated on operational Growave connection row |

`Session.GrowaveMetadata` is **removed**. No Growave data on `Session`.

**Phase 3.5 closure:**

- `PartnerConnectionSettings` is provider-neutral: `externalAccountKey` + non-secret `providerSettings`
- credentials support `API_KEY`, `CLIENT_CREDENTIALS`, and `ACCESS_TOKEN`; token expiry lives with credentials
- webhook subscription IDs are shared connection webhook state
- `PartnerIntegrationAdapter` + registry owns provider validation, subscribe/unsubscribe and webhook processing
- Growave implements the adapter; controllers remain provider-specific until the unified integrations API

---

## 4. Agent checklist (new integration)

- [ ] Family selected via §1 (COMMERCE / ESP / PARTNER / DOMAIN_DNS / CHANNEL) — documented in PR/ADR
- [ ] Hub `kind` matches family (DOMAIN_DNS lives under Settings → Domain authentication, not App Integrations)
- [ ] No new `IntegrationType` for ESP/partner/DNS/channel; no `Session.<Partner>Metadata`
- [ ] Connection row in `integration_connection_config` with correct `family`
- [ ] ESP only: `EspConfig` import policy linked by `connectionId` + `EspImportAdapter`
- [ ] Authority preset matches family via `IntegrationFamily` predicates (`usesPartnerSettings` / `usesNoneContactAuthority` / ESP default) — do not OR family constants in `IntegrationConnectionService`
- [ ] Partner only: `partnerDefaults()`, `partnerSettings`, enrichment keys + webhook→`EventType`, no import adapter, gated out of ESP-only paths
- [ ] Partner only: implement `PartnerIntegrationAdapter`; do **not** extend shared connection service with provider-specific fields
- [ ] DOMAIN_DNS only: implement `DomainDnsProviderAdapter`; `noneDefaults()`; do **not** create `EspConfig` or partner webhooks
- [ ] CHANNEL only: implement `ChannelSendAdapter`; `noneDefaults()`; separate channel consent; channel sends on `contactEmail` with `type=MESSAGE` (not a separate collection); do **not** create `EspConfig`

---

## 5. Implementation plan (ordered)

| Phase | Status | Work |
|-------|--------|------|
| **1** | **Done** | `IntegrationFamily`, `IntegrationConnectionConfig` (+ credentials / partnerSettings), repository, unique `(accountId, provider)` |
| **2** | **Done** | `IntegrationConnectionService` (draft/connect/status/disconnect/lookup) + `partnerDefaults()` |
| **3** | **Done** | Growave connect/webhook/status/factory/loyalty gate on connection row; remove `Session.GrowaveMetadata` |
| **3.5a** | **Done** | Correct stale connection KB + family KB status to match ADR-013 |
| **3.5b** | **Done** | `PartnerIntegrationAdapter` + registry; Growave implements validation, subscribe/unsubscribe and webhook processing |
| **3.5c** | **Done** | Generic credential modes: API key, OAuth client credentials and access token (+ expiry) |
| **3.5d** | **Done** | Provider-neutral settings: `externalAccountKey` + `providerSettings`; shared webhook state on connection |
| **3.5e** | **Done** | Registry + lifecycle/credential tests prove a second provider plugs in without changing shared service |
| **4** | **Done** | ESP: `EspConfig.connectionId` + `EspConnectionLinkService`; connection row created on ESP connect; read-through fallback to legacy `EspConfig` credentials/status |
| **5** | **Done** | ESP credential/status/webhook-registration writes moved onto the connection row; legacy `EspConfig` copies cleared and deprecated; `EspConfig` = import policy (+ `webhookEnabled` toggle mirrored to the connection) |
| **6** | **In progress** | Verify: compile + Growave/ESP tests (done); run `backfill-esp-integration-connection-config.js` per environment (pending) |

**Ordering rule:** Phases **3.5 and 4–5 are complete**. New partners must use the adapter registry and generic connection fields; new ESP code must read credentials/status through `EspCredentialService` / `EspConnectionLinkService`, never from `EspConfig` fields directly.

**Future (not blocking):** unify HTTP under `/api/v1/integrations/{provider}` (connection-config KB).