# Growave ↔ Adflipr parity gaps (vs Klaviyo / Omnisend)

**Status:** Gap inventory (updated 2026-08-01) — not an implementation plan  
**Audience:** Product + Engineering  
**Adflipr baseline:** [`growave-integration.md`](./growave-integration.md) (v1 locked)  
**Reference model:** Growave → ESP (Klaviyo / Omnisend / Attentive / Postscript) — Adflipr plays the **ESP role**

**Primary references**

- [Klaviyo Integration collection](https://help.growave.io/en/collections/13720952-klaviyo-integration)
- [Integrating Growave with Klaviyo](https://help.growave.io/en/articles/11306909-integrating-growave-with-klaviyo)
- [Email & SMS Marketing collection](https://help.growave.io/en/collections/13720800-email-sms-marketing)
- [Integrating Growave with Omnisend](https://help.growave.io/en/articles/11315900-integrating-growave-with-omnisend)
- [Growave × Klaviyo product](https://www.growave.io/integrations/klaviyo) · [× Omnisend](https://www.growave.io/integrations/omnisend)

**Legend**

| Status | Meaning |
|--------|---------|
| ✅ | Present / shipped |
| 🟡 | Partial — weaker than ESP reference |
| ❌ | Missing |
| — | Not offered on that platform |

**Major gaps (top line):** trigger catalog (~10 selectable vs ~12–14 ESP), profile property catalog (6 vs ~20), event payload richness for email, templates/prebuilt flows packaging, review-request + remind rounds, wishlist sale/back-in-stock, birthday/expiry as first-class automations.

**Webhook catalog check (2026-07-31):** Official Growave merchant webhook topics are only the set under [Webhook topics](https://docs.growave.io/docs/Webhooks/Topics/) (Customer / Reviews / Questions / Wishlists). There is **no** public topic for review request, wishlist on-sale, wishlist back-in-stock, points/reward expiration, delayed points/reward, or birthday-reward. Those Omnisend/Klaviyo metrics are **Growave→ESP integration-only** (or cron/notification-center driven), not subscribeable partner webhooks.

---

## Side-by-side trigger matrix (Klaviyo · Omnisend · Adflipr)

**Counts:** Klaviyo ~12–14 named metrics (help article lists 14; body copy sometimes says 12) · Omnisend **12** · Adflipr **10** selectable `GROWAVE_*` EventTypes (public webhooks only).

**Delivery:** Klaviyo/Omnisend receive metrics via Growave’s **native ESP integration**. Adflipr receives **partner webhook topics** only — so ESP-only journeys cannot be mirrored 1:1 without Growave expanding public webhooks or a poll/notification-center path.

| Journey | Klaviyo | Omnisend | Adflipr |
|---------|---------|----------|---------|
| Earned points | ✅ `GrowaveEarnedPoints` | ✅ `growave-earned-points` | ✅ `GROWAVE_EARNED_POINTS` |
| Redeem reward | ✅ `GrowaveRedeemReward` | ✅ `growave-redeem-reward` | ✅ `GROWAVE_REDEEM_REWARD` |
| Reached tier | ✅ `GrowaveReachedTier` | ✅ `growave-reached-tier` | ✅ `GROWAVE_REACHED_TIER` |
| Friend received referral | ✅ `GrowaveFriendReceivedReferral` | ✅ `growave-friend-received-referral` | ✅ `GROWAVE_REFERRAL_ACTIVATED` |
| Referral completed | ✅ `GrowaveReferralCompleted` | ✅ `growave-referral-completed` | ✅ `GROWAVE_REFERRAL_COMPLETED` |
| Wishlist on save | ✅ `GrowaveProductsOnSave` | ✅ `growave-wishlist-on-save` | ✅ `GROWAVE_WISHLIST_ITEM_ADDED` |
| Birthday **reward** | ✅ `GrowaveBirthdayReward` | ✅ `growave-birthday-reward` | 🟡 `GROWAVE_BIRTHDAY_UPDATED` (profile update, not reward issued) |
| Delayed points | ✅ `GrowaveDelayedPoints` | — | ❌ |
| Delayed reward | ✅ `GrowaveDelayedReward` | — | ❌ |
| Points expiration | ✅ `GrowavePointsExpiration` | ✅ `growave-points-expiration` | ❌ |
| Reward expiration | ✅ `GrowaveRewardExpiration` | ✅ `growave-reward-expiration` | ❌ |
| Review **request** (+ reminds) | ✅ `GrowaveReviewRequest` | ✅ `growave-review-request` | ❌ |
| Wishlist on sale | ✅ `GrowaveProductsOnSale` | ✅ `growave-wishlist-on-sale` | ❌ |
| Wishlist back in stock | ✅ `GrowaveProductsInStock` | ✅ `growave-wishlist-back-in-stock` | ❌ |
| Review submitted / created | — (request-focused) | — (request-focused) | ✅ `GROWAVE_REVIEW_SUBMITTED` |
| Review deleted | — | — | ✅ `GROWAVE_REVIEW_DELETED` |
| Wishlist item removed | — | — | ✅ `GROWAVE_WISHLIST_ITEM_REMOVED` |
| Points updated (generic) | — | — | ❌ Removed 2026-08-01 (was Adflipr-only; never reliable live) |
| Reward created (non-redeem) | — | — | ❌ Removed 2026-08-01 (was Adflipr-only split; all `rewardCreated` → Redeem) |

**Aligned on:** naming style (Growave-branded metrics), core loyalty earn/redeem/tier, both referral legs, wishlist save.

**Not aligned on:** ESP-only journeys (expiry, delayed, review request, wishlist sale/BIS, true birthday reward). Adflipr instead exposes a few **webhook-only** extras (review deleted, wishlist removed) that Klaviyo/Omnisend do not list as first-class triggers.

**Adflipr EventSelector catalog (10):** Earned Points, Redeem Reward, Reached Tier, Review Submitted, Review Deleted, Wishlist Item Added, Wishlist Item Removed, Birthday Updated, Referral Activated, Referral Completed.

See §1 below for feature notes / status codes; locked mapping in [`growave-integration.md`](./growave-integration.md) §5.

---

## 0. Architecture way-by-way

| Way | Klaviyo / Omnisend | Adflipr v1 | Gap |
|-----|--------------------|------------|-----|
| Data direction | Growave → ESP | Growave → Adflipr | ✅ Correct role (we are ESP) |
| Connect location | Growave Integrations card | Adflipr App Integrations | 🟡 UX mirror only; functionally OK |
| Auth | Klaviyo: OAuth · Omnisend: API key into Growave | Merchant pastes Growave API Key + Secret into Adflipr | 🟡 Different UX; machine-to-machine OK for our role |
| Feature toggles (Rewards / Reviews / Wishlist) | Merchant selects which Growave apps push to ESP | All subscribed topics always on connect | ❌ No per-surface enable matrix |
| Who sends email | ESP (Growave stops native email after handoff) | Adflipr | N/A — document clearly for merchants |
| Event naming | Partner-specific metric names (`GrowaveEarnedPoints`, `growave-earned-points`) | **`GROWAVE_*` EventTypes** (ADR-014) | ✅ Aligned direction (expand trigger catalog separately) |
| Idempotent ingress | ESP-side | Webhook ledger + HMAC | ✅ |
| Enrich then automate | Profile + event → flow | Enrich `loyalty_*` then `processContactEvent` | ✅ |
| Outbound award / redeem from ESP | Not required for parity | Explicit non-goal | N/A (keep) |

---

## 1. Automation triggers (feature-by-feature)

Reference trigger sets from [Klaviyo guide](https://help.growave.io/en/articles/11306909-integrating-growave-with-klaviyo) (~14) and [Omnisend guide](https://help.growave.io/en/articles/11315900-integrating-growave-with-omnisend) (12).

| # | Reference trigger | Typical use | Adflipr today | Status | Notes |
|---|-------------------|-------------|---------------|--------|-------|
| 1 | Earned points | “You earned points” | `GROWAVE_EARNED_POINTS` (`balanceUpdated`, delta &gt; 0) | ✅ | Criteria: delta threshold |
| 2 | Redeem reward | Discount activated | `GROWAVE_REDEEM_REWARD` (`rewardCreated` + negative balance) | ✅ | |
| 3 | Points updated (generic) | Catch-all balance | — | ❌ | Removed from Adflipr product surface (2026-08-01); null/0 delta enrich-only. Not in Klaviyo/Omnisend |
| 4 | Delayed points | Points pending credit | — | ❌ | **No public webhook topic** (ESP-only) |
| 5 | Delayed reward | Reward pending issue | — | ❌ | **No public webhook topic** (ESP-only) |
| 6 | Points expiration | Expiring soon | — | ❌ | **No public webhook topic** (ESP-only); UDF only via getCustomer |
| 7 | Reward expiration | Reward expiring | — | ❌ | **No public webhook topic** (ESP-only) |
| 8 | Birthday reward | Birthday gift flow | `GROWAVE_BIRTHDAY_UPDATED` ← `birthdayUpdated` | 🟡 | Topic is birthday **profile** update, not “birthday reward issued” |
| 9 | Reached tier | VIP upgrade | `GROWAVE_REACHED_TIER` | ✅ | Criteria: tier title equals |
| 10 | Friend received referral | Referred friend signup | `GROWAVE_REFERRAL_ACTIVATED` (`referralActivated` → **referred**) | ✅ | Contact from `referred.email` |
| 11 | Referral completed | Referrer rewarded after purchase | `GROWAVE_REFERRAL_COMPLETED` (`referralCompleted` → **referrer**) | ✅ | Contact from `referrer.email` |
| 12 | Review **request** | Ask for review after fulfill | — | ❌ | **No public webhook topic**; only `review/created|updated|deleted` |
| 13 | Review submitted / created | Review left | `GROWAVE_REVIEW_SUBMITTED` | ✅ | Omnisend emphasizes request; we emphasize created |
| 14 | Wishlist on save | Saved item reminder | `GROWAVE_WISHLIST_ITEM_ADDED` | 🟡 | Via `wishlistItem/created` + `updated` |
| 15 | Wishlist on sale | Price drop | — | ❌ | **No public webhook topic** (ESP-only) |
| 16 | Wishlist back in stock | Restock alert | — | ❌ | **No public webhook topic** (ESP-only) |
| 17 | Wishlist item removed | Cleanup / exit | `GROWAVE_WISHLIST_ITEM_REMOVED` | ✅ | `wishlistItem/deleted` subscribed |
| 18 | Review request **remindRound** 1–3 | Up to 3 reminder workflows | — | ❌ | Tied to review-request ESP path; not in webhook catalog |
| — | Reward created (non-redeem) | Adflipr-only split | — | ❌ | Removed 2026-08-01; all `rewardCreated` → Redeem Reward |

### Official Growave webhook topics (complete)

From [docs.growave.io Webhooks/Topics](https://docs.growave.io/docs/Webhooks/Topics/) (sitemap + topic pages, 2026-07-31):

| Group | Topics |
|-------|--------|
| Customer | `balanceUpdated`, `tierUpdated`, `rewardCreated`, `birthdayUpdated`, `referralActivated`, `referralCompleted` |
| Reviews | `review/created`, `review/updated`, `review/deleted` |
| Wishlists | `wishlistItem/created`, `wishlistItem/updated`, `wishlistItem/deleted`, `wishlistItem/moved` |
| Questions | `question/*`, `answer/*` (Adflipr v1 non-goal) |

**Adflipr not yet subscribed (optional):** `v1/wishlistItem/moved`, `v1/review/updated` — low value vs ESP gaps above.

**Major gap:** Full Omnisend/Klaviyo trigger parity **cannot** be reached via partner webhooks alone for review-request, wishlist sale/BIS, expiry, delayed points/reward. Those need Growave partner API expansion, notification-center polling, or accepting non-parity.

---

## 2. Contact / profile fields (field-by-field)

Klaviyo “custom properties” from the [integrate guide](https://help.growave.io/en/articles/11306909-integrating-growave-with-klaviyo). Adflipr stores canonical `loyalty_*` UDFs ([`LoyaltyFieldLabels`](../../service/src/main/java/com/goveca/recruit/ad/loyalty/LoyaltyFieldLabels.java)).

| # | Klaviyo property | Adflipr field / source | Status | Gap |
|---|------------------|------------------------|--------|-----|
| 1 | PointBalance | `loyalty_points_balance` | ✅ | |
| 2 | TierTitle | `loyalty_tier` | ✅ | |
| 3 | TierId | — | ❌ | No stable tier id |
| 4 | NextTierTitle | — | ❌ | |
| 5 | NextTierId | — | ❌ | |
| 6 | NextTierProgressRemaining | — | ❌ | |
| 7 | PointsExpirationDate | `loyalty_points_expires_at` | 🟡 | Only when profile fetch runs; not event-driven |
| 8 | ReferralLink | `growave_referral_link` | 🟡 | Filled via `getCustomer` when UDF blank (not on every webhook forever) |
| 9 | RewardsProgram (0/1) | — | ❌ | Enrollment / activity flag |
| 10 | RewardsProgramDateEnrolled | — | ❌ | |
| 11 | RewardsProgramConsent | — | ❌ | |
| 12 | ReviewNumbers | `loyalty_review_count` | 🟡 | Count only; not full review stats |
| 13 | ReviewScore | — | ❌ | Avg rating |
| 14 | LastReviewRate | — | ❌ | |
| 15 | PendingReviewsCount | — | ❌ | |
| 16 | BirthDate / BirthDateISO | System `birthDate` | 🟡 | Partial; no birthday-reward automation |
| 17 | Locale | — | ❌ | |
| 18 | Accepts Marketing | Shopify / Adflipr subscription | N/A | Via commerce path, not Growave (same as Klaviyo note) |
| 19 | Shopify Tags | Shopify sync | N/A | Same |
| 20 | Wishlist depth (items, product ids) | `loyalty_wishlist_count` | 🟡 | Count only; no product-level wishlist profile |

**Audience filters:** Loyalty section exists when Growave connected — but only for the fields we store. Missing props ⇒ missing segment recipes.

---

## 3. Event payload / personalization (way-by-way)

| Capability | Klaviyo / Omnisend | Adflipr v1 | Status |
|------------|--------------------|------------|--------|
| Event properties in email (`[[event.*]]` / Klaviyo event props) | Rich per trigger (products, codes, balances, URLs) | Merge tags from contact UDFs + limited criteria attrs | 🟡 |
| Product list in review / wishlist emails | Templates with products / grid layouts | Not standardized on loyalty events | ❌ |
| Discount / reward code in redeem flows | Template + event vars | Not first-class | ❌ |
| Wishlist / review deep links | SMS/email tags (`wishlist_page_url`, `order_review_url`) | Not modeled | ❌ |
| Trigger filters beyond basic | Omnisend `remindRound`; Klaviyo segment + metric filters | Points delta / balance / tier only | 🟡 |
| Smart Sending / marketing consent docs | Explicit in Growave–Klaviyo docs | Rely on Adflipr subscription model | 🟡 Document |

---

## 4. Templates & flows packaging (feature-by-feature)

| Capability | Klaviyo | Omnisend | Adflipr v1 | Status |
|------------|---------|----------|------------|--------|
| Per-trigger email templates | HTML + hybrid | Saved templates per trigger | No Growave-branded pack | ❌ |
| Template import / docs | Dedicated articles | Optimizing templates + variables articles | — | ❌ |
| Prebuilt flows in ESP | Loyalty welcome, redemption reminder, referral reminder | Manual workflows (templates provided) | Recipes: VIP members, wishlist shoppers (narrow) | 🟡 |
| Customer Hub / in-ESP widgets | Klaviyo Customer Hub | — | N/A | ❌ (Klaviyo-only surface) |
| Reviews in abandoned cart | Web Feed article | — | Separate cart product; no Growave feed | ❌ |

---

## 5. Connect / merchant UX (way-by-way)

| Capability | Reference | Adflipr v1 | Status |
|------------|-----------|------------|--------|
| Install from loyalty app admin | Yes | No (Adflipr-initiated) | 🟡 |
| Feature enable matrix | Yes | No | ❌ |
| “Growave listed in ESP Integrations” confirmation | Yes | Adflipr connected status | ✅ Equivalent |
| Deep links to partner API settings / docs | Partner-side | Open app / API settings / Docs row | ✅ |
| Shopify prerequisite | Implicit (Growave is Shopify) | Explicit Shopify session required | ✅ |
| Disconnect / webhook cleanup | Partner disconnect | Disconnect + unsubscribe | ✅ |
| Troubleshooting playbook | Sync / flow not triggering articles | Ops notes in KB only | 🟡 |
| Notification center handoff copy | “Growave will no longer send emails” | Need Adflipr-facing copy | 🟡 |

---

## 6. Channel depth (collection comparison)

From [Email & SMS Marketing](https://help.growave.io/en/collections/13720800-email-sms-marketing) vs [Klaviyo Integration](https://help.growave.io/en/collections/13720952-klaviyo-integration):

| Partner class | Trigger depth | Props | Templates | Relevance to Adflipr |
|---------------|---------------|-------|-----------|----------------------|
| **Klaviyo** (gold) | ~14 | ~20 | Full + Hub | Ceiling for email automation parity |
| **Omnisend** | 12 | Vars docs | Full | **Practical parity target** |
| **Postscript / Attentive** | Thin SMS subset | Tags | SMS copy | SMS later; not v2 email priority |
| **Emarsys / Connectif** | Campaign-oriented | Varies | Varies | Lower priority |

**Recommendation:** Treat **Omnisend’s 12 triggers + variables + remindRound** as the v2 product bar; use **Klaviyo’s property + template + prebuilt flow** depth as v3 ceiling.

---

## 7. Major gaps ranked (what matters most)

### P0 — Blocks “same way as Klaviyo/Omnisend” for core journeys

1. **Review request** automation (+ **remindRound** 1–3) — **no public webhook**; ESP-only today  
2. **Wishlist on sale** and **wishlist back in stock** — **no public webhook**; ESP-only today  
3. **Points / reward expiration** as triggers — **no public webhook**; ESP-only today  
4. ~~Referral completed / friend referred~~ — **done** (`referralActivated` / `referralCompleted`)  
5. **Birthday reward** trigger — only `birthdayUpdated` (profile) exists publicly; not “reward issued”

For 1–3 (and true birthday-reward), product must either accept non-parity, build a Growave Notification Center / poll workaround, or get new webhook topics from Growave.

### P1 — Segmentation & email quality

6. Expand **profile properties** (RewardsProgram*, NextTier*, review score stats)  
7. **Live sync** of expiry date + referral link (not fetch-only)  
8. **Event payload** for templates (products, codes, deep links)  
9. **Per-trigger email templates** + merge-tag catalog  

### P2 — Packaging & UX polish

10. **Prebuilt automations** for each major trigger (parity with Klaviyo prebuilt flows)  
11. **Feature toggles** on connect (Rewards / Reviews / Wishlist)  
12. Merchant **docs / troubleshooting** (consent, why flow didn’t fire)  
13. Optional: Customer Hub–like loyalty blocks inside Adflipr contact UI  

### Explicit non-gaps (do not chase)

- Becoming a Growave OAuth client that sends *into* Klaviyo  
- Outbound award-points / redeem APIs from Adflipr (v1 non-goal)  
- Q&A / Instagram gallery  
- Full historical review/wishlist import on connect  

---

## 8. Suggested phased map (decision aid only)

| Phase | Outcome | Includes |
|-------|---------|----------|
| **v1 (done)** | Core loyalty + review created + wishlist add | Current webhooks, 6 UDFs, basic criteria, recipes |
| **v2 (Omnisend bar)** | 12-trigger class journeys | Review request + reminds, wishlist sale/BIS, expiry, birthday, referrals; subscribe missing topics; richer criteria |
| **v3 (Klaviyo bar)** | Full props + packaging | ~20 props, template pack, prebuilt flows, Hub-like contact loyalty panel |

Exact webhook topic names for missing journeys must be confirmed against Growave API docs before build (may not map 1:1 to public ESP metric names).

---

## 9. Related Adflipr docs

- [`growave-integration.md`](./growave-integration.md) — locked v1 decisions  
- [`decisions.md`](./decisions.md) ADR-011 / ADR-013  
- [`integration-connection-family.md`](./integration-connection-family.md)  

When product locks a phase, update this file’s Status line and add an ADR entry under `decisions.md`.
