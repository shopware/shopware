# Playwright specs — `repro.spec.ts`

For any rendered/visual/interaction symptom. The spec asserts the **healthy** behaviour: it fails on
the buggy version (⇒ reproduced) and passes when healthy (⇒ not_reproduced).

The spec is shown verbatim in the issue comment, so **write it to be read**: a short `//` comment
above each step (navigate, precondition, action, the final assertion) explaining what it does and
why, so a reviewer can follow the scenario without reverse-engineering the selectors.

## The rules `repro validate` enforces

- **Import only `@playwright/test`.**
- **Exactly one awaited `expect(...)`** — the final healthy-symptom assertion. Everything before it
  is setup, expressed as waits/actions, not asserts.
- **No API setup from the spec** — no `fetch`, `page.request.*`, or `page.evaluate(fetch…)`. Static
  state goes in `fixtures.json` so both legs seed identically; create runtime state only through the
  owning UI flow.
- **No non-local URLs** — navigate relative to `baseURL` (`page.goto('/detail/…')`).

## Preconditions gate the symptom

Setup steps must prove the scenario is exercisable, and **throw `PRECONDITION_NOT_FOUND: <what>`**
when it isn't — so a missing precondition becomes `inconclusive`, not a fake pass/fail. Use bounded
waits, not instant `count()` samples (SPA/Admin pages are still loading):

```ts
await page.goto('/detail/<seeded-id>');
await page.locator('.product-detail-buy').waitFor({ state: 'visible', timeout: 15000 })
  .catch(() => { throw new Error('PRECONDITION_NOT_FOUND: buy widget not on the product page'); });

await expect(page.locator('.product-detail-price')).toHaveText('19,99 €'); // the one healthy assertion
```

Keep each precondition its own gate so a failure names the missing state.

**Reach the assertion the same way on both versions.** The exact spec is re-run on trunk (where the
bug is usually fixed), so don't rely on the *broken* behaviour to navigate — e.g. don't click a
control that only stays reachable because the bug leaves a menu open. If your path only works while
the symptom is present, the trunk leg times out and comes back `inconclusive`. Prefer stable routes
(`page.goto` a URL) over multi-step navigation that the fix would change.

## Split it: precondition = the surface renders; assertion = the property the bug changes

Two steps for a rendered symptom:

1. **Precondition — the stable surface renders.** Wait for the part of the page that is present
   *regardless of the bug* (the scaffold the symptom lives in) and throw `PRECONDITION_NOT_FOUND` if
   it never appears. If it can't render, the shop was never set up to the state that shows the
   symptom, so the leg is `inconclusive` (precondition failed) — not a fake pass/fail.
2. **Healthy assertion — check the specific thing the bug changes**, on an element that *resolves in
   both the healthy and buggy versions*. Assert a value or state, not the mere existence of a node:
   text (`toContainText` — pick text **unique** to the symptom so an unrelated match can't fake a
   pass), an attribute (`toHaveAttribute`), a class/state (`toHaveClass`, `toBeEnabled`), a count
   (`toHaveCount`), a rendered value, and so on.

Why: an element that *did* resolve lets the assertion judge its property → a clean `reproduced` on
the buggy leg vs `not_reproduced` on trunk. Asserting the mere **presence** of the element the bug
removes (`toBeVisible` on a node that isn't there) fails as "element not found", which is
indistinguishable from cross-version UI drift ⇒ `inconclusive`. (The exception is a bug whose symptom
*is* an element wrongly appearing — then its presence/absence is the property, and you assert that.)

```ts
// Precondition: the surface the symptom lives in must render.
await page.locator('.stable-scaffold').waitFor({ state: 'visible', timeout: 15000 })
  .catch(() => { throw new Error('PRECONDITION_NOT_FOUND: <what did not render>'); });

// Healthy assertion: check the property the bug changes — here, text unique to the symptom.
await expect(page.locator('.element-that-renders')).toContainText('Unique Expected Value');
```

## Viewport — declare it, don't resize mid-test

For a mobile/responsive/off-canvas symptom, set the viewport in `reproduction-plan.json`:

```json
{ "executor": "playwright", "viewport": { "width": 390, "height": 844 } }
```

The harness applies it at browser-context creation, so **both** legs run at that size and the recorded
video frame matches. **Never call `page.setViewportSize()` in the spec** (`repro validate` rejects it):
it resizes *after* the video frame is fixed, so the smaller page renders inside a desktop frame with a
grey border. Omit `viewport` for normal desktop bugs.

## Auth — the harness owns it

- **admin-ui:** the harness logs in and hands the spec an authenticated session. Navigate straight to
  `/admin#/sw/...`; do **not** author login steps. (A login/bootstrap bug is the exception — then
  clear state, drive `/admin#/login` yourself, and assert the shell becomes usable.) The Admin
  "new version available" banner is disabled by the harness, so don't add code to dismiss it.
- **storefront-ui:** the harness pre-accepts cookie consent by default. Don't clear cookies unless
  the bug is the consent flow (`browser_state.auto_cookie_consent: false`).

## Optional video evidence

A screenshot + Playwright trace is captured for every run — enough for a static rendering, layout, or
text bug. **Set `"record_video": true`** in `reproduction-plan.json` when the symptom only reads *in
motion*: an animation or transition, a drag, a hover/toggle, scrolling, a loading/timing sequence, or
an interaction where "clicking X does nothing / does the wrong thing" (e.g. a menu that won't close).
Each leg then records a `.webm` that the comment links. Leave it off otherwise.

**When you set `record_video: true`, narrate the recording** — a silent motion clip is hard to follow,
and the whole point of the video is that a human can watch the symptom happen. Use the two helpers from
`./video-helpers.js` (the harness places this file next to your spec at run time — keep the import
path exactly as written): `narrate(page, "what's happening")` (a subtitle) and `mark(page, locator, "label")`
(highlights the element about to be used). Narrate each meaningful step — the navigation, the action
that triggers the symptom, and the failing state. **Write each as its own single-line `await` statement,
next to — never wrapping — the real action**, e.g.:

```ts
import { test, expect } from '@playwright/test';
import { narrate, mark } from './video-helpers.js';   // stripped from the verdict run + the comment

test('discount badge missing in slider', async ({ page }) => {
  await narrate(page, 'Open the category with the product slider');
  await page.goto('/navigation/<seeded-id>');
  await mark(page, page.locator('.product-slider-item').first(), 'The seeded slider product');
  await expect(page.locator('.product-slider-item .badge-discount')).toBeVisible();
});
```

The verdict run and the code shown in the comment are this spec with the `narrate`/`mark` lines and
the import **removed** — the actions and the single assertion are byte-identical. So narration only
ever affects the video, never the result. Don't add extra assertions or locators just to narrate.

Use `repro check` and `playwright-cli` to nail selectors and timing before committing the spec; a
final `repro try` gives a non-authoritative preview and points you at the screenshot to review.
