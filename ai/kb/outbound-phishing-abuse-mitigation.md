# Outbound phishing / scam abuse — incident notes and mitigation backlog

**Status:** Open  
**Created:** 2026-07-10  
**Canonical for:** How Adflipr can detect, block, and respond to scam/phishing email sent through the platform.

**Related docs:** `docs/email-precheck.md`, `docs/email-audit-gates.md`, `docs/email-send-behavior-matrix.md`

---

## 1. Incident example (sent via Adflipr)

A campaign/automation email was delivered through Adflipr with Russian phishing copy impersonating Yandex and promising a confirmed payout.

### Observed content (Russian → English)

| Russian | English |
|---------|---------|
| Ваша сумма в 50 000 подтверждена! | **Your amount of 50,000 has been confirmed!** |
| Ваша сумма в 50 000 подтверждена Яндексом. | Your amount of 50,000 has been confirmed by Yandex. |
| Пожалуйста, заполните заявку для получения средств | Please complete the application to receive the funds |
| это займет всего несколько минут. | it will only take a few minutes. |
| Мы всегда готовы помочь вам! | We are always ready to help you! |

- **Suspicious link observed:** `https://tri.su/M5JO4` (short / unrelated domain; not in current `SPAM_URL` shortener list)
- **UI label:** `Source: Campaign` (sent as a **campaign**, not only automation)
- **Footer:** Standard Adflipr unsubscribe block (account: ironcraze, placeholder address fields)

### Why this is abuse

- Brand impersonation (Yandex)
- Financial lure (“50,000 confirmed”)
- Off-platform link to an unknown domain
- Typical phishing pattern; recipients should not click the link

---

## 2. Why Adflipr did not block it today

Current controls are **uneven by send path**:

| Control | Campaign publish | Automation activate/send | Test send |
|---------|------------------|--------------------------|-----------|
| Content precheck (`evaluateContentRules`) | **Yes** (can BLOCK publish) | **No** | **No** |
| Unsubscribe footer required | **Yes** (publish) | N/A at send | Partial |
| `EmailSendingEligibility` (quota, bounce, suspended) | Yes | Yes (activate + sampled at send) | Yes |
| English offensive/spam term list | Publish / builder audit only | **Bypassed** | **Bypassed** |
| Cyrillic / Russian scam terms | **Not in term list** | **Not in term list** | **Not in term list** |
| Brand impersonation (`yandex`, etc.) | **Not in term list** | **Not in term list** | **Not in term list** |
| `evaluateFullSendRules` at send time | **Not wired** in production | **Not wired** | **Not wired** |

### Code references

- **Campaign publish precheck (enforced):** `CampaignService.assertPublishContentPrecheckPasses` → `PrecheckEvaluationService.evaluateContentRules`
- **Automation send (no content scan):** `WorkflowStateProcessorService.processSendEmailNode` → `ContactEmailService.initiateAutomation`
- **Term matching (English-focused):** `service/src/main/resources/offensive-content-terms.txt`, `OffensiveContentRule.java`
- **Shortener hard-block only:** bit.ly, tinyurl, t.co, goo.gl, ow.ly, is.gd, buff.ly — **not** `tri.su`
- **WARN vs BLOCK:** aggregate score ≥ 60 = BLOCK; 30–59 = WARN and user may **continue anyway** on publish (`docs/email-audit-gates.md`)
- **Reactive only after send:** Mailgun `complained` → contact `MARKED_AS_SPAMMED` (`MailgunWebhookService`); bounce gates in `EmailSendingEligibilityService`

**Likely path for this incident:** Campaign published with **Russian body** (bypasses English term list) or automation/test path without precheck; link domain not on blocklist.

---

## 3. Immediate ops response (today)

Use **Super Admin** (no code deploy required):

| Step | Action | Where |
|------|--------|-------|
| 1 | Identify **accountId** / workspace from campaign metadata, Mailgun logs, or `ContactEmail` (`sourceId` = campaign/automation id) | Mongo / ES / Mailgun |
| 2 | **Block workspace** — revokes tokens, stops sending | Super Admin → Account detail → **Block workspace** (`POST /api/v1/super-admin/accounts/{id}/block`) |
| 3 | Pause or delete offending **campaign** / **automation** | Support or account DB |
| 4 | Optional: **purge workspace** if confirmed malicious actor | `SuperAdminPurgeWorkspaceDialog` (destructive) |
| 5 | Dry-run same HTML through precheck | Super Admin → **Precheck simulator** (`/super-admin/precheck`) |
| 6 | Monitor **bounce eligibility** for repeat offenders | `/super-admin/bounce-eligibility` |

**Super Admin routes (app):** `/super-admin`, `/accounts/:accountId`, `/precheck`, `/bounce-eligibility`

**Permission:** `PLATFORM_ADMIN_DASHBOARD`

---

## 4. Engineering TODO backlog

Prioritized work to block messages like this **before** send.

### P0 — Close send-path gaps

- [ ] **TODO-P0-1:** Run `evaluateContentRules` on **automation activation** (`AutomationService.updateAutomationStatus` → ACTIVE), same as campaign publish. Block activation when aggregate BLOCK.
- [ ] **TODO-P0-2:** Run `evaluateContentRules` (or `evaluateFullSendRules`) in **`ContactEmailService.processAndSendEmail`** immediately before Mailgun send. BLOCK → do not send; pause campaign/automation.
- [ ] **TODO-P0-3:** Run content precheck on **test sends** (`EmailTemplateService.sendTestEmail`) — at minimum BLOCK tier; stricter caps for unverified domains.
- [ ] **TODO-P0-4:** Wire **`evaluateFullSendRules`** (reputation, rate limit, suppression) at send time; populate `EmailContext` complaint/bounce rates from account stats.

### P1 — Phishing-specific content rules

- [ ] **TODO-P1-1:** Add **hard-block** category for **brand impersonation** (financial / tech brands): `yandex`, `paypal`, `apple id`, etc. (config file, not only English slang).
- [ ] **TODO-P1-2:** Add **Cyrillic / Russian scam patterns** to `offensive-content-terms.txt` or separate locale file: e.g. `подтвержден`, `Яндекс`, `получения средств`, lottery/payout lures.
- [ ] **TODO-P1-3:** Expand **URL risk**: block suspicious TLDs / unknown short domains; optional threat feed integration; flag `http` + financial copy.
- [ ] **TODO-P1-4:** Treat **high-confidence phishing templates** as **hardBlock** (not scored-only 8 pts/hit) so one match blocks publish/send.
- [ ] **TODO-P1-5:** Remove or tighten **“Continue anyway”** on WARN for new/unverified accounts (product decision).

### P2 — Detection, ops, and policy

- [ ] **TODO-P2-1:** **Complaint-rate eligibility gate** — auto-suspend workspace when Mailgun `complained` rate exceeds threshold (mirror bounce gate).
- [ ] **TODO-P2-2:** Super Admin **flagged-send queue** — list recent sends with precheck score, subject, account; one-click block.
- [ ] **TODO-P2-3:** **New-account sending policy** — lower quotas, mandatory domain verification, or manual review for first N sends.
- [ ] **TODO-P2-4:** Runtime **term list hot-reload** or Super Admin term editor (today: edit `offensive-content-terms.txt` + deploy).
- [ ] **TODO-P2-5:** Log all precheck BLOCK/WARN at publish **and** send with `accountId`, `campaignId`/`automationId` for audit trail.

### P3 — Hardening

- [ ] **TODO-P3-1:** Image/OCR or LLM classifier for non-Latin phishing in images (longer term).
- [ ] **TODO-P3-2:** Platform-wide suppression registry (bad senders / repeat scam templates).
- [ ] **TODO-P3-3:** Homoglyph / obfuscation detection in `PrecheckTermMatcher`.

---

## 5. Acceptance criteria (when backlog is done)

1. The example Russian Yandex / 50,000 / `tri.su` template **cannot publish or send** (BLOCK with clear reason).
2. **Automation SEND_EMAIL** cannot enqueue Mailgun without passing the same content gate as campaign publish.
3. **Test send** cannot be used to bypass content rules.
4. Super Admin can **block workspace** and see **why** a message was flagged.
5. Complaint spike triggers **automatic sending suspension** without waiting for bounce threshold.

---

## 6. Verification checklist (QA)

After implementing P0/P1:

1. Create campaign with Russian Yandex phishing copy → publish must **fail** (or send must **fail**).
2. Same template in automation → activate/send must **fail**.
3. Same template via test send → **fail** or heavy rate limit.
4. Legitimate English marketing email with one mild spam word → WARN or pass per policy; document expected behavior.
5. Super Admin block → `EmailSendingEligibility` returns `WORKSPACE_SUSPENDED`; no further sends.

---

## 7. File index (implementation map)

| Area | Path |
|------|------|
| Precheck engine | `service/.../precheck/service/PrecheckEvaluationService.java` |
| Term list | `service/src/main/resources/offensive-content-terms.txt` |
| Campaign publish gate | `service/.../service/CampaignService.java` |
| Automation activate | `service/.../service/AutomationService.java` |
| Runtime send | `service/.../service/ContactEmailService.java` |
| Automation email node | `service/.../service/WorkflowStateProcessorService.java` |
| Eligibility / bounce | `service/.../service/EmailSendingEligibilityService.java` |
| Mailgun complaints | `service/.../service/MailgunWebhookService.java` |
| Super Admin block | `service/.../superadmin/SuperAdminAccountBlockService.java` |
| Precheck simulator UI | `adflipr-app/src/pages/superAdmin/SuperAdminPrecheckSimulator.tsx` |
| Block workspace UI | `adflipr-app/src/pages/superAdmin/SuperAdminBlockWorkspaceDialog.tsx` |

---

## 8. Decision log

| Date | Decision |
|------|----------|
| 2026-07-10 | Document phishing incident; backlog created. Root gap: content precheck not enforced on all send paths; English-only terms; no brand/Cyrillic rules. |
