# Executor: `playwright` (UI — storefront / admin)

Use ONLY for a genuine UI bug (rendered state, interaction). The most expensive layer —
escalate here only when neither `http` nor `direct` can fire the symptom.

## What you author
Generate `repro.spec.ts` (set `script_path: "repro.spec.ts"`). It asserts the HEALTHY
behaviour, is generated ONCE, and the SAME spec runs on BOTH the reported and trunk
versions — so it must tolerate cross-version UI drift. Use relative paths (`baseURL` is
injected). Comment every step.

## Locators — version-stable, semantic ONLY
- Use `getByRole(role, {name})` / `getByLabel` / `getByText` / `getByPlaceholder` with
  case-insensitive regex and accessible/visible names. `getByRole` and `getByLabel` both
  resolve `aria-label` / `aria-labelledby` / associated `<label>` — Shopware's `mt-*`
  fields expose their label as the accessible name, so both work for inputs.
- **Scope inside the relevant landmark + use SPECIFIC names** to avoid strict-mode
  ambiguity: `getByRole('navigation').getByRole('link', {name:/^Products$/i})`, NOT a broad
  regex with `.first()`.
- **NEVER** use CSS classes, data-test ids, or attribute selectors — not even as a
  fallback. An element no semantic locator can reach IS a `PRECONDITION_NOT_FOUND` (and may
  mean the bug isn't faithfully automatable — set low confidence).

## Structure: precondition vs symptom (this drives the verdict)
**(1) Navigation / precondition** — reach the state and WAIT for each element it depends on:
```ts
await locator.waitFor({ state: 'visible', timeout })
  .catch(() => { throw new Error('PRECONDITION_NOT_FOUND: <what>') });
```
- NEVER gate a precondition on `isVisible()`/`isHidden()` — they return the CURRENT state
  immediately and IGNORE the timeout, so right after `page.goto()` they false-negative
  while the SPA is still bootstrapping.
- NEVER gate a precondition on `expect()`/`toBeVisible()` — a timed-out `expect` is
  classified as the SYMPTOM → false `reproduced`.
- Do NOT wait via `waitForLoadState('networkidle')` (the admin SPA long-polls and never
  settles) or `waitForURL()` on a pattern already true before the action — wait for a
  concrete post-action element.

**(2) Symptom** — exactly ONE `await expect(...)` of the HEALTHY behaviour, with a generous
timeout. This is the ONLY failure that may mean `reproduced`.
- For a hidden / closed / collapsed / off-canvas symptom use
  `await expect(locator).not.toBeInViewport()` or assert explicit state
  (`toHaveAttribute('aria-hidden','true')`) — **NOT** `not.toBeVisible()`: an element moved
  off-screen via transform/translate is still "visible" to Playwright, so `toBeVisible`
  fails on BOTH versions and FAKES a reproduction.

## How `run-playwright.sh` classifies the result
- genuine `expect()` assertion failure → `reproduced`
- `PRECONDITION_NOT_FOUND` / `test.skip`, navigation/connection error, or a non-assertion
  locator/timeout failure → `inconclusive` (cross-version UI drift, never a bogus
  `reproduced`)
- all pass → `not_reproduced`; no tests collected / unparseable → `inconclusive` / `blocked`

Evidence (screenshot, video, trace) is captured automatically under `test-results/`.
