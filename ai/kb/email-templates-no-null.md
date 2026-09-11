# Email templates: no null strings

**Rule:** Email content (subject, HTML body, or any user-facing text) must **never** contain the literal string `"null"` or unchecked null values.

## Why

- Java string concatenation turns `firstName + " " + lastName` into `"first null"` when `lastName` is null.
- Placeholders like `{{sub_user_name}}` or merge tags that receive null (or are built from null) can emit `"null"` in the final email.
- Recipients see unprofessional text (e.g. "Hello praveen.jain6407@gmail.com null") and it indicates a bug in data or template logic.

## Backend contract

1. **Placeholder / merge values**
   - When building maps for template replacement (e.g. `placeholders.put("sub_user_name", ...)`), never pass a null or a string that equals `"null"`.
   - Use `EmailTemplateUtil.sanitizePlaceholderValue(key, value)` when in doubt: it returns `""` for null or `"null"` and logs an error for the literal.

2. **Display names and concatenation**
   - Any method used for display or email (e.g. `User.getFullName()`) must be null-safe: null parts become empty string, and the result must never be the literal `"null"`.

3. **Sending email**
   - Before sending, the backend calls `EmailTemplateUtil.checkLogAndSanitizeNullInEmailContent(context, subject, html)`:
     - **Whole-word "null"** (e.g. "Hello name null"): replaced with blank string and an ERROR is logged.
     - **"null" as substring** of a word (e.g. "nullable", "annulled"): only logged, not replaced, so real words are never broken.
   - The method returns sanitized subject and HTML; the email is sent with these values.

## Where the check runs

- `EmailService.sendMailGunEmail`
- `EmailService.sendSubUserPasswordEmail`
- `EmailService.sendOtpEmail`
- `EmailService.sendContactEmail`
- `EmailService.sendAnalyticsReportEmail`

Any new email-sending path must call `checkAndLogNullInEmailContent` before building/sending the mail.

## Fixing a reported instance

1. Search logs for `[EMAIL_NULL_GUARD]` to get context (e.g. `sendSubUserPasswordEmail`).
2. Identify the placeholder or field that produced `"null"` (often a name, email, or merge tag).
3. Fix at source: use null-safe getters (e.g. `getFullName()`), sanitize in the placeholder map with `sanitizePlaceholderValue`, or fix the entity to never return null for display fields (use empty string).

## Cross-reference

- `EmailTemplateUtil`: `sanitizePlaceholderValue`, `sanitizeNullInEmailContent` (whole-word only), `checkLogAndSanitizeNullInEmailContent`, `processTemplate`
- `EmailService`: all send methods use sanitized subject/html from `checkLogAndSanitizeNullInEmailContent`
- `User.getFullName()`: null-safe implementation
