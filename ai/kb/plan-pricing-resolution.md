# Plan pricing resolution (post-payment vs display)

**Canonical detailed plan + code audit:** [`service/docs/PLAN_PRICING_POST_PAYMENT_RESOLUTION.md`](../../service/docs/PLAN_PRICING_POST_PAYMENT_RESOLUTION.md)  
**Domain docs:** [`service/docs/ACCOUNT_PLAN_PRICING.md`](../../service/docs/ACCOUNT_PLAN_PRICING.md), [`service/docs/PARTNER_PLAN_PRICING_DESIGN.md`](../../service/docs/PARTNER_PLAN_PRICING_DESIGN.md)

## Layers

`planVersionPricing` rows:

| Layer | Discriminator | Role |
|-------|---------------|------|
| Global | `partnerId` / `accountId` null | Structure, contacts, emailQuota, default price + Razorpay plan id |
| Partner | `partnerId` set | Override **priceCents + razorpayPlanId** only |
| Account | `accountId` set | Same overrides for one workspace |

**Display / checkout precedence:** account → partner → global (`PartnerPlanPriceResolver` + `PlanPriceServiceV2`).

**`razorpayPlanId` is unique** across all layers. Lookup by plan id across the collection is safe.

## Post-payment entitlement resolution (required)

After Razorpay charge (callback / webhook), do **not** derive entitlements only from global list price + charged cents.

Order:

1. `findByRazorpayPlanId(planId)` — any layer  
2. Account overlay for `accountId`  
3. Partner overlay for live partner of `accountId`  
4. Global catalog  
5. Still miss → **fail / flag** — never write `monthlyEmailQuota = 0` on paid activation  

## Anti-patterns (code)

| Anti-pattern | Why |
|--------------|-----|
| `getActivePlanVersion` (global only) + `getMatchingPricingTierByCurrencyAndCents(chargedAmount)` as the only resolver for callbacks/webhooks | Overlay prices ≠ global; miss → null tier |
| `emailQuota != null ? emailQuota : 0L` on paid activation | Silent zero quota (SUB189) |
| Callback assigning `emailCount` → `numberOfContacts` | `emailCount` is emails, not contacts |
| Callback filling null tier `emailQuota` from `emailCount` | Client hint is not source of truth; refuse and fix catalog |

## Known vulnerable call sites (as of 2026-07-25)

**Fixed in code** (plan-id → account → partner → global; refuse unpaid zero):

- `RazorpaySubscriptionProcessingService.resolvePlanVersionAndPricing(planDetails, accountId)`
- Callback / webhook callers pass `accountId`; refuse when `pricingTier == null`
- `updateSubscriptionWithPlanDetails` / webhook update branch no longer write `0` on null tier
- `SubscriptionCallbackService` no longer maps `emailCount` → contacts; ignores `emailCount` for entitlements; refuses update if resolved tier `emailQuota` is null
- `PlanDetailsService.resolveRazorpayPlanId` prefers account/partner overlay by contacts band
- `SubscriptionCreationService` throws if paid emailQuota cannot be resolved

Safe reference: `SubscriptionService.lookupAndValidatePlan` (plan id + anti-spoof).

## Aggregation note

`PlanVersionRepositoryWrapper` uses `globalCatalogOnly()` when **aggregating** PlanVersion for structure — intentional. Do not merge every overlay into one shared PlanVersion. Cross-layer identity is via **plan id** (and account-scoped overlay fallback), not “load all catalogues into one aggregate.”
