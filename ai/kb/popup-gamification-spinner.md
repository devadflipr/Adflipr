# Popup gamification spinner (AI pointer)

**Canonical suite**

- Architecture: [`docs/POPUP_GAMIFICATION_SPINNER_ARCHITECTURE.md`](../../docs/POPUP_GAMIFICATION_SPINNER_ARCHITECTURE.md)
- Audit: [`docs/POPUP_GAMIFICATION_SPINNER_AUDIT.md`](../../docs/POPUP_GAMIFICATION_SPINNER_AUDIT.md)
- ADR: [`docs/POPUP_GAMIFICATION_SPINNER_ADR.md`](../../docs/POPUP_GAMIFICATION_SPINNER_ADR.md)

**Related:** [`spin-to-win-brd.md`](./spin-to-win-brd.md), [`popup-builder-reviewer-module-map.md`](./popup-builder-reviewer-module-map.md)

## Invariants (short)

1. Spinner not in palette; gamification-preloaded; cannot delete/dup.
2. ≤1 spinner, email, phone across **all** steps.
3. Spin only from button `submit` | `next-step` (no wheel click).
4. Winning slice owns post-spin destination (Action / Step / URL); Close on the button still wins over Step; await spin before SUBMIT when spinner present (SPIN-011).
5. Wheel Settings exposes Action + Step (or URL when Action=`url`); slices export `action` / `redirectStep` / `redirectUrl`.
6. Next-step CTA with a spinner also fires SUBMIT (same merge as submit CTA).
7. Gamification email-input cannot be deleted while a spinner is on the popup.

## Wheel paint — constant on-screen slice labels (2026-08-02)

**Philosophy:** Slice label typography uses trait `labelFontSize` as **constant CSS pixels** on screen. The disc (wedges, outline, pegs, hub circle) scales with the **laid-out** canvas host width. Center hub copy stays relative to hub size (`resolveSpinnerCenterTextFontSizes`) — not constant screen px.

**Why:** Presentation CSS uses `width: 100%` on the wheel canvas. Painting only at trait `wheelSize` then letting CSS shrink the bitmap made labels unreadable in narrow gamification columns.

**How:** Measure host CSS width `W`, set backing store to `W * devicePixelRatio`, draw geometry in CSS-pixel space with `labelFontCssPx = labelFontSize`. Trait `wheelSize` still drives root `maxWidth`.

**Accepted tradeoff:** Smaller discs → more label ellipsis / crowding.

**Invariant:** Editor (`SpinWheel`) and embed (`embed/spinner.ts`) share [`spinnerWheelCanvas.ts`](../../adflipr-app/src/layouts/PopupBuilder/utils/spinnerWheelCanvas.ts) paint metrics + `paintSpinnerWheel` — do not fork draw math.
