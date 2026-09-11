# Growave Klaviyo-style model migration (tracking)

**Status:** Code complete — env ops remaining (2026-07-30)  
**Decision:** Full Klaviyo mirror — Growave-specific EventTypes + `growave_*` UDFs  
**Supersedes:** ADR-011 D5/D6 (loyalty_* + provider-agnostic EventType), AE-001  
**Related ADR:** ADR-014 in [`decisions.md`](./decisions.md)

## Decision log

| Date | Decision |
|------|----------|
| 2026-07-30 | Adopt Klaviyo-style integration-specific metrics and contact properties for Growave |
| 2026-07-30 | No deprecated loyalty EventType enum constants — `EventType.fromJson` remaps old stored names |
| 2026-07-30 | Expanded subscribe list: rewardCreated, birthdayUpdated, wishlist created/deleted |
| 2026-08-01 | Removed selectable/emitted `GROWAVE_POINTS_UPDATED` and `GROWAVE_REWARD_CREATED` (not in Klaviyo/Omnisend; unreliable live); enums kept for stored automations |

## Naming contract

### EventTypes (canonical / selectable)

| EventType | Webhook topic |
|-----------|---------------|
| `GROWAVE_EARNED_POINTS` | `balanceUpdated` (delta &gt; 0) |
| `GROWAVE_REDEEM_REWARD` | `rewardCreated` + `balanceUpdated` (delta &lt; 0) |
| `GROWAVE_REACHED_TIER` | `tierUpdated` |
| `GROWAVE_REVIEW_SUBMITTED` | `review/created` |
| `GROWAVE_WISHLIST_ITEM_ADDED` | `wishlistItem/created` + `wishlistItem/updated` |
| `GROWAVE_WISHLIST_ITEM_REMOVED` | `wishlistItem/deleted` |
| `GROWAVE_BIRTHDAY_UPDATED` | `birthdayUpdated` |

Legacy (not emitted / not selectable): `GROWAVE_POINTS_UPDATED`, `GROWAVE_REWARD_CREATED`. Null/zero `balanceUpdated` delta → enrich only.

### Contact UDFs

`growave_points_balance`, `growave_tier`, `growave_points_expires_at`, `growave_referral_link`, `growave_review_count`, `growave_wishlist_count`

## Phase checklist

| Phase | Item | Status |
|-------|------|--------|
| 0 | Tracking KB + ADR-014 | `done` |
| 1 | UDF write `growave_*`; Mongo migrate script; ES mapping | `done` |
| 1 | `GROWAVE_*` EventTypes; webhook; FE; recipes | `done` |
| 2 | Remove deprecated loyalty enums / dual-read / AE docs | `done` |
| 3 | Ops: Mongo migrate + ES reindex per env; smoke | `todo` |

| Env | Mongo UDF migrate | ES reindex | Smoke |
|-----|-------------------|------------|-------|
| local/dev | `todo` | `done` (`contacts_dev` → `contacts_dev_v20260730`) | `todo` |
| staging | `todo` | `todo` | `todo` |
| prod | `todo` | `done` on local cluster (`contacts_prod` → `contacts_prod_v20260730`) | `todo` |

## Data migration runbook

```bash
mongosh "$URI" --file service/scripts/mongo/migrate-loyalty-udf-to-growave.js
APPLY=true mongosh "$URI" --file service/scripts/mongo/migrate-loyalty-udf-to-growave.js
```

Then ES mapping deploy + reindex per elasticsearch skill.

Stored automation roots with old enum names (`POINTS_EARNED`, …) are remapped on deserialize via `EventType.fromJson`.

## Smoke checklist

- [ ] Connect Growave → ACTIVE; extra topics subscribed
- [ ] Webhook → contact has `growave_*`
- [ ] Automation `GROWAVE_EARNED_POINTS` enrolls
- [ ] Audience filter `growave_tier` works after reindex
- [ ] EventSelector shows Growave tab + birthday/reward/wishlist remove
