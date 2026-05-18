# AdFlipr WordPress plugin — full review prompt & response (archive)

Use this file to re-run the same review after fixes: attach or reference this doc and ask for an updated pass against the current codebase.

---

## Part A — Original review prompt (user request)

Please perform a COMPLETE production-grade review of this WordPress + WooCommerce plugin for AdFlipr.

Context:
- This is a WooCommerce connector plugin for AdFlipr SaaS
- Plugin handles:
  - authentication
  - WooCommerce API integration
  - cart abandonment tracking
  - order tracking
  - webhook sync
  - retry queues
  - email suppression
  - UTM tracking
  - onboarding UI
- Goal:
  1. WordPress.org approval
  2. Production readiness
  3. Reliable event tracking
  4. Scalable SaaS connector architecture

I want a DEEP audit of:
- runtime behavior
- feature flow
- risks
- missing documentation
- WordPress submission readiness
- scalability concerns
- security gaps
- UX/onboarding problems
- data flow correctness

IMPORTANT:
Do NOT give generic suggestions.
Review actual implementation behavior and code paths.

--------------------------------------------------
SECTION 1 — FULL RUNTIME BEHAVIOR
--------------------------------------------------

Explain complete runtime flow step-by-step:

1. Plugin boot sequence
- What loads first?
- Which hooks initialize modules?
- What happens if WooCommerce is missing?
- What happens if requirements fail?

2. Authentication flow
- Login flow
- Token storage
- Token refresh/expiry behavior
- What happens if token invalid?
- What happens if user never logs in?

3. Frontend tracking flow
- What scripts load?
- Which pages load tracking?
- Cart tracking lifecycle
- Checkout tracking lifecycle
- Block checkout vs classic checkout behavior

4. Backend sync flow
- Product sync
- Order sync
- Coupon sync
- Retry queue lifecycle
- Cron lifecycle
- Manual retry flow

5. Email handling flow
- How WooCommerce emails are suppressed
- What happens if AdFlipr API fails?
- Fail-open vs fail-closed analysis

6. Uninstall/deactivation flow
- What cleanup happens?
- Are cron jobs removed?
- Are options removed?
- Are remote disconnects handled safely?

--------------------------------------------------
SECTION 2 — FEATURE AUDIT
--------------------------------------------------

List ALL implemented features and categorize them:

- Stable
- Experimental
- Fragile
- Incomplete

Also identify:
- hidden features
- dead code
- partially implemented systems
- duplicated logic

--------------------------------------------------
SECTION 3 — WORDPRESS.ORG REVIEW READINESS
--------------------------------------------------

Review specifically from WordPress plugin reviewer perspective.

Check for:
- nonce validation
- AJAX security
- REST API permission safety
- sanitization
- escaping
- capability checks
- tracking disclosures
- external API disclosure
- privacy policy readiness
- debug leftovers
- unsafe defaults
- hidden tracking behavior
- remote code execution risks
- proxy endpoint risks

Tell me:
1. DEFINITE rejection risks
2. POSSIBLE reviewer concerns
3. Areas likely to trigger manual review

--------------------------------------------------
SECTION 4 — SECURITY REVIEW
--------------------------------------------------

Check deeply for:

- CSRF
- SSRF
- XSS
- privilege escalation
- token leakage
- insecure option storage
- abuse vectors
- unauthenticated endpoints
- webhook abuse
- replay attack risks
- weak idempotency
- race conditions

For every issue:
- explain impact
- explain exploit scenario
- provide severity:
  - critical
  - high
  - medium
  - low

--------------------------------------------------
SECTION 5 — DATA FLOW & EVENT RELIABILITY
--------------------------------------------------

Audit:
- event correctness
- duplicate event risks
- missed event risks
- race conditions
- retry safety
- queue durability
- ordering guarantees
- webhook reliability
- idempotency implementation

Specifically verify:
- cart_abandonment
- checkout_purchased
- product sync
- coupon sync
- retry queue processing

Explain:
- when data may be permanently lost
- when duplicates may happen
- when timing issues may occur

--------------------------------------------------
SECTION 6 — WOOCOMMERCE COMPATIBILITY
--------------------------------------------------

Audit compatibility with:
- WooCommerce classic checkout
- WooCommerce block checkout
- HPOS
- multisite
- guest checkout
- caching plugins
- WP Cron disabled setups
- object caching
- older WooCommerce versions
- WooCommerce API changes

Tell me:
- fragile hooks
- deprecated hooks
- missing compatibility layers

--------------------------------------------------
SECTION 7 — PERFORMANCE & SCALE
--------------------------------------------------

Assume:
- 1000+ stores installed
- large catalogs
- high checkout volume

Audit:
- database usage
- option growth
- cron scalability
- outbound HTTP pressure
- queue growth
- payload size
- sync amplification
- frontend JS overhead

Identify:
- bottlenecks
- memory risks
- slow admin paths
- scaling limits

--------------------------------------------------
SECTION 8 — ONBOARDING & UX REVIEW
--------------------------------------------------

Review onboarding flow.

Specifically:
- What does merchant experience before login?
- Is plugin understandable before signup?
- Is login dependency clearly explained?
- Is there enough value communication?
- Does plugin appear broken before login?
- Are inactive states clear?
- Are errors visible?

Suggest:
- onboarding improvements
- admin notices
- health dashboards
- connection status UX

--------------------------------------------------
SECTION 9 — DOCUMENTATION AUDIT
--------------------------------------------------

Tell me EXACTLY what must be documented.

Include:
- README requirements
- external service disclosure
- tracking disclosure
- privacy policy items
- webhook behavior
- cron behavior
- token handling
- WooCommerce dependencies
- unsupported scenarios
- block checkout limitations
- retry queue behavior

Also list:
- missing screenshots
- missing FAQs
- missing troubleshooting docs

--------------------------------------------------
SECTION 10 — FINAL VERDICT
--------------------------------------------------

Provide:

1. Production readiness score (/10)
2. WordPress.org approval readiness score (/10)
3. Reliability score (/10)
4. Security score (/10)
5. Scalability score (/10)

Then provide:
- MUST FIX
- SHOULD FIX
- NICE TO HAVE

Finally answer:
- Is this safe for public release?
- Is this ready for WordPress.org submission?
- What is the single biggest architectural risk remaining?
- What is the single biggest business/product risk remaining?

IMPORTANT:
Be brutally honest.
Do not avoid criticism.
Focus on real-world production issues, not theoretical perfection.

---

## Part B — Review response (audit snapshot)

**Scope note:** This response was generated against the codebase at the time of review (`wordpress/` — `adflipr.php`, `includes/class-adflipr-*.php`, `includes/adflipr-*.php`, `assets/js/adflipr-cart-tracker.js`, `uninstall.php`, `readme.txt`). Re-run the review after fixes to refresh findings.

### SECTION 1 — Full runtime behavior (summary)

**Boot:** `adflipr.php` declares HPOS compatibility, loads config/requirements/debug/rest helpers/idempotency, defines constants, instantiates `AdFlipr_Core` immediately. Core loads class files in constructor and registers `plugins_loaded` → `register_classes` which constructs Auth, Data, Admin, Transactional Email, Cart Tracker (each registers hooks at `get_instance()`).

**WooCommerce missing:** Activation blocks via `adflipr_meets_requirements()`; runtime uses admin notices but hooks remain — guards rely on `WC`/`wc_*` checks.

**Auth:** POST `/wp-json/adflipr/v1/login-token` stores token in `wp_options` (`adflipr_auth_token`, `adflipr_token_expiry`). GET same route requires existing valid token + nonce + referer — expect 403 before connect. No OAuth-style refresh; `wp_token` path can synthesize long TTL if API omits expiry.

**Frontend tracking:** Cart script only on `is_checkout()`; UTM script on whole storefront when opt-in option enabled. Cart abandonment = delayed AJAX after email blur/page load heuristic. Checkout completion: classic `woocommerce_checkout_order_created` + blocks `woocommerce_store_api_checkout_order_processed`; dedup via order meta `_adflipr_checkout_webhook_sent` after 200 or queued.

**Backend sync:** Bulk `send_data_all_data` batches of 50; incremental product/coupon hooks; orders bulk query limited to completed/processing + meta for unsent checkout flag. Retry queue in option `adflipr_webhook_retry_queue`; cron `adflipr_webhook_retry_process` + manual REST/AJAX/WP-CLI + drain after login.

**Email:** `woocommerce_email_enabled` fail-open without valid token; suppression from option when token valid. Admin path commits via webhook ack; separate WC REST route can write options directly (divergence).

**Deactivate:** Clears `adflipr_webhook_retry_process` only. **Uninstall:** disconnect PUT + deletes options + clears `adflipr_send_data_event` and retry cron. Uninstall uses `error_log` heavily.

**Concrete bugs called out in review:**
1. `send_data_after_authentication()` used `get_option('adflipr_orders_last_offset', 0)` for **coupon** and **subscription** offsets — should use `adflipr_coupons_last_offset` and `adflipr_subscriptions_last_offset`.
2. `maybe_schedule_next_event()` called `wp_clear_scheduled_hook('adflipr_send_product_data')` but scheduling uses **`adflipr_send_data_event`** — wrong/dead cleanup.

---

### SECTION 2 — Feature audit (summary)

| Area | Category |
|------|----------|
| HPOS declare, core auth, proxy, webhook+retry core | Stable / stable with sensitive edges |
| Bulk pagination, cart DOM heuristics, queue loss paths | Fragile |
| Shortcode `[adflipr_react_app]` without enqueuing build assets | Incomplete |
| `wc/v3/adflipr-email-settings` + GET ck/cs | Fragile / high reviewer risk |
| `bin/sample.php` in repo | Dead / harmful for .org zip |

Duplicated scheduling: activation + `init` both ensure retry cron.

---

### SECTION 3 — WordPress.org readiness (summary)

**Definite risks:** Ship `bin/sample.php` in distribution; `error_log` in permission paths; REST permission allowing **consumer_key/secret in query string**.

**Manual review triggers:** External SaaS + optional UTM; broad PII payloads; proxy route (mitigated by `manage_options` + nonce + referer — document).

**Possible concerns:** Strict Referer vs `site_url()` scheme/host mismatch; version skew (header `Version`, `ADFLIPR_VERSION`, readme Stable tag).

---

### SECTION 4 — Security (summary table)

| Issue | Severity |
|-------|----------|
| GET query string WC API credentials | High |
| Debug `error_log` in auth permission | Low–Medium |
| Admin-only proxy path chaining to SaaS | Medium (admin trust boundary) |
| REST `setup_email_setting` direct option write | Medium–High if REST keys leak |
| Token in `wp_options` | Medium (typical connector) |
| Cart `nopriv` + rate limit | Low–Medium (abuse/cost) |
| Proxy to fixed host (not open SSRF) | — |

CSRF: nonces on admin/adflipr REST paths. SSRF: not open arbitrary URL. Idempotency: unknown actions use random UUID → duplicate risk on retry.

---

### SECTION 5 — Data flow & reliability (summary)

- **cart_abandonment:** idempotency email + cart_hash; miss if JS never fires; dup if same hash before cart changes.
- **checkout_purchased:** idempotency order id + meta gate after success/queued.
- **Product add:** idempotency over full JSON — unstable key ordering → duplicate risk.
- **Queue:** max items → loss; max attempts → drop; `update_option` whole array → race under concurrency; `no_token` does not increment attempts (good).

---

### SECTION 6 — WooCommerce compatibility (summary)

HPOS declared; classic + block checkout hooks present; block DOM validation fragile; guest checkout supported; WP-Cron disabled → rely on manual/CLI; bulk orders only completed/processing.

---

### SECTION 7 — Performance & scale (summary)

Sync webhook on high-frequency saves; monolithic queue option; large cart JSON; 50-item batches; no backoff — bottlenecks for large catalogs.

---

### SECTION 8 — Onboarding & UX (summary)

Not-connected notice + privacy panel good; GET login-token 403 confuses Network tab; shortcode broken without enqueue; suggest health inline + clarify probe endpoint.

---

### SECTION 9 — Documentation (summary)

Readme strong on external services; add explicit REST route list, queue loss, cron-disabled ops, cart limitations, block checkout caveats, bulk order status scope, version alignment; screenshots for .org.

---

### SECTION 10 — Verdict (scores at time of review)

| Dimension | Score (/10) |
|-----------|-------------|
| Production readiness | 6 |
| WordPress.org readiness | 5.5 |
| Reliability | 6 |
| Security | 5.5 |
| Scalability | 5 |

**MUST FIX:** Wrong coupon/subscription offsets in `send_data_after_authentication()`; wrong `wp_clear_scheduled_hook` name; remove/exclude `bin/sample.php` from distribution; tighten `get_read_api_permission_check` (no query-string secrets, remove debug logs); align plugin versions.

**SHOULD FIX:** Shortcode enqueue or remove from docs; reduce uninstall/permission `error_log` noise; document referer behavior; consider async product webhooks.

**NICE TO HAVE:** Backoff for retries; queue outside single option; softer GET login-token for “not connected”.

**Direct answers:** Cautious public release after MUST FIX; .org not ready until sample/debug/query-key issues addressed; biggest architectural risk: synchronous coupling + monolithic queue; biggest product risk: silent loss/dedupe undermining trust in automation metrics.

---

## Part C — Changelog (fill in after each re-review)

| Date | Reviewer / tool | Notes |
|------|-----------------|-------|
| _(initial)_ | Cursor audit | Snapshot saved; see Part B |

_Add a row each time you re-run the same review post-fixes._
