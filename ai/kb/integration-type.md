# IntegrationType — commerce store origin only

**Status:** Locked (2026-07-26)  
**Canonical enum:** `com.goveca.recruit.ad.enums.IntegrationType`

---

## Rule (MANDATORY)

`IntegrationType` answers: **which commerce store do customers / orders come from?**

Allowed values:

| Value | Meaning |
|-------|---------|
| `SHOPIFY` | Customers and commerce events originate from a Shopify shop |
| `WOOCOMMERCE` | Customers and commerce events originate from a WooCommerce store |

**Forbidden:** Adding partner apps, ESPs, billing gateways, or loyalty tools as new `IntegrationType` values (e.g. do **not** add `GROWAVE`, `KLAVIYO`, `RAZORPAY`).

---

## Where other providers live

| Provider family | Identity / credentials |
|-----------------|------------------------|
| Commerce store | `IntegrationType` on `Session` (`SHOPIFY` / `WOOCOMMERCE`) |
| ESP | `EspProviderType` + `esp_config` (import policy); connection target in [`integration-connection-family.md`](./integration-connection-family.md) |
| Partner apps (e.g. Growave) | `integration_connection_config` row with `family=PARTNER` (shared connection collection); **not** `Session`, **no** `EspConfig`/`EspImportAdapter`. See [`integration-connection-family.md`](./integration-connection-family.md) (ADR-013) |
| Contact provenance | `ContactSource` (may include `GROWAVE`, ESP names, etc.) |
| External mapping / unified provider id | `IntegrationProvider` (broader than `IntegrationType`) |

---

## Consequences

- Runtime gates that mean “store connected” use `SHOPIFY` / `WOOCOMMERCE` only.
- Partner connect/disconnect must key off `(accountId, IntegrationType.OTHER, integrationName=…)`, not a fake commerce type (until shared connection layer lands).
- Do not map partner apps through `IntegrationProvider.fromIntegrationType` — that helper is commerce-only (`SHOPIFY` / `WOOCOMMERCE`).

**References:** [`decisions.md`](./decisions.md) ADR-012 / ADR-013, [`integration-connection-family.md`](./integration-connection-family.md), [`growave-integration.md`](./growave-integration.md), [`service/docs/integration-connection-config-kb.md`](../../service/docs/integration-connection-config-kb.md).
