# ContactRepositoryWrapper — `save` vs `update`

**Status:** Locked (2026-07-26)  
**Cursor rule:** [`.cursor/rules/contact-repository-save-vs-update.mdc`](../../.cursor/rules/contact-repository-save-vs-update.mdc)  
**Code:** `ContactRepositoryWrapper.save` / `ContactRepositoryWrapper.update`

## Decision

| Method | Use when | Behavior |
|--------|----------|----------|
| **`save(contact)`** | **Creating** a new contact only | Looks up active contact by `(accountId, email)`. If one exists, **returns that row unchanged** and does **not** apply caller mutations. Otherwise sets `created`/`updated` and inserts. |
| **`update(contact)`** | **Persisting mutations** on an existing contact (has `id`) | Sets `updated` and writes the document. |

## Why this matters

`save` is **not** Spring Data “upsert”. Calling `save` after enriching an existing contact (e.g. Growave `applyEnrichment`) can silently **discard** `dynamicField` / profile changes and return the stale DB document.

```java
// ❌ BAD — enrichment on existing contact
contact.setDynamicField(...);
contactRepository.save(contact); // may return unmodified existing row

// ✅ GOOD — create
Contact created = contactRepository.save(Contact.builder()...build());

// ✅ GOOD — mutate existing
mergeFields(contact, ...);
contactRepository.update(contact);
```

## Growave pattern

- `resolveOrCreateContact` → `save` only when creating a brand-new contact.
- `applyEnrichment` / `applyCustomerProfile` / `applyEnvelopeFields` → always **`update`** (contact already resolved/created and has `id`).

## Rule of thumb

- No `id` / first insert path → `save`
- Has `id` / already loaded from DB → `update`
