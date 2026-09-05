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

1. **Precondition — the smallest stable enclosure that proves the symptom is observable.** Wait for
   the *most specific* element that must be present for the symptom to show (its container/surface),
   and throw `PRECONDITION_NOT_FOUND` if it never appears. Too broad (just "the page loaded") lets the
   precondition pass while the symptom's surface is still missing → a fake pass/fail; but it must be
   something that renders on the **fixed** version too. If it can't render, the shop was never set up
   to the state that shows the symptom, so the leg is `inconclusive` (precondition failed).
2. **Healthy assertion — check the specific thing the bug changes**, on an element that *resolves in
   both the healthy and buggy versions*. Assert a value or state, not the mere existence of a node:
   text (`toContainText` — pick text **unique** to the symptom so an unrelated match can't fake a
   pass), an attribute (`toHaveAttribute`), a semantic state (`toBeEnabled`, `toBeChecked`), a count
   (`toHaveCount`), a rendered value, and so on.

**Locate by what survives the fix, not by what the fix rewrites.** The exact spec is re-run on trunk,
where the bug is usually fixed — and a fix routinely renames CSS classes or restructures markup. A
class-based locator (`.product-variant-characteristics-text`) can then vanish on the *fixed* leg for a
benign reason → a false `inconclusive`/`not_reproduced`. Prefer locators bound to semantics, in order:

> role + accessible name (`getByRole('link', { name: 'Seeded Product' })`) → visible text (`getByText`)
> → a stable `data-*` hook → structural relationship → CSS class (last resort).

Roles aren't universal — leaf content (a bare `<div>` holding text) often has no role, so anchor the
**scaffold** by role/name and assert the **content** by text. (Shopware's storefront exposes a rich
accessibility tree — regions, links, buttons, nav all have names — so role locators are usually
available for structure.)

Why it holds: an element that *did* resolve lets the assertion judge its property → a clean
`reproduced` on the buggy leg vs `not_reproduced` on trunk. Asserting the mere **presence** of the
element the bug removes (`toBeVisible` on a node that isn't there) fails as "element not found",
indistinguishable from a class refactor ⇒ `inconclusive`. (Exception: when the symptom *is* an element
wrongly appearing, its presence/absence is the property — assert that.)

```ts
// Precondition (role + accessible name — survives a class refactor): the symptom's surface rendered.
await page.getByRole('link', { name: 'Seeded Product' }).waitFor({ state: 'visible', timeout: 15000 })
  .catch(() => { throw new Error('PRECONDITION_NOT_FOUND: seeded product not shown on the page'); });

// Healthy assertion (unique text — a domain value a fix will not rename): the expected content shows.
await expect(page.getByRole('region', { name: /the relevant region/i })).toContainText('Unique Expected Value');
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

When you enable it, **narrate the recording so a human can follow the motion.** Capturing the clip
with the `narrate`/`mark` helpers — and the rules that keep captions worth showing — is its own short
guide: **[playwright-narration.md](playwright-narration.md)**. Read it whenever you set
`record_video: true`.

Use `repro check` and `playwright-cli` to nail selectors and timing before committing the spec; a
final `repro try` gives a non-authoritative preview and points you at the screenshot to review.
