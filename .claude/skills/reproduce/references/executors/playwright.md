# Executor: `playwright` (UI — storefront / admin)

Use ONLY for a genuine UI bug (rendered state, interaction). The most expensive layer —
escalate here only when neither `http` nor `direct` can fire the symptom.

## What you author
Generate `repro.spec.ts` (set `script_path: "repro.spec.ts"`). It asserts the HEALTHY
behaviour, is generated ONCE, and the SAME spec runs on BOTH the reported and trunk
versions — so it must tolerate cross-version UI drift. Use relative paths (`baseURL` is
injected). Comment every step.

**`admin-ui` specs START AUTHENTICATED — do NOT write login steps.** The harness logs in
deterministically (proven locators) and injects the session via `storageState` before your
spec runs. Begin directly at the target module, e.g.
`await page.goto('/admin#/sw/cms/index')`, and wait for a concrete element of that page.
Authoring a login preamble is the single most common source of broken runs (strict-mode
locator fumbles) — it will be redundant at best and flaky at worst.

**Storefront *customer* login — SEED the customer, never create it at runtime.** When a bug
needs a logged-in (or registered) storefront customer, put the customer in `fixtures.json`
with a known plaintext `password` and a COMPLETE default billing address built from the
resolved placeholders (`salutationId: "{{SALUTATION}}"`, `countryId: "{{COUNTRY}}"`,
`languageId: "{{LANGUAGE}}"`, plus `firstName`/`lastName`/`street`/`zipcode`/`city`), bound to
`{{SC}}` via `boundSalesChannelId`/`salesChannelId`. Then the spec just logs in through the
storefront form (`/account/login`, labels "Your email address" / "Your password"). Do NOT
assemble a customer-create payload at RUNTIME in the spec (store-api register or admin
`POST /api/customer`): hand-built addresses routinely omit a required field and 400 with
"This value should not be blank", killing the run at `PRECONDITION_NOT_FOUND` (hit live on
#33). seed.sh validates + resolves the fixture deterministically; runtime creation does not.

**Admin *precondition entities* — SEED them via `fixtures.json`, never POST them at runtime.**
The same rule generalizes beyond customers to ANY entity a spec needs in place before the UI
interaction (media, product_manufacturer, product, category, …): declare it in `fixtures.json`
and let seed.sh upsert it through the **sync API** (`/api/_action/sync`), which accepts a
client-supplied 32-char-hex `id` and upserts deterministically. Do NOT create it at runtime in
the spec with `request.post('/api/<entity>/<id>')` — POST is the COLLECTION verb, so posting to
an item path is **405 Method Not Allowed** and the run dies at
`PRECONDITION_NOT_FOUND: … create failed (405)` (hit live on #29: `POST /api/media/<id>`). If an
entity truly must be created from inside the spec, the admin-API verbs are `POST /api/<entity>`
(id in the BODY) or `PATCH /api/<entity>/<id>` (upsert a known id) — never `POST /api/<entity>/<id>`.
The one thing sync CANNOT do is attach a binary, so a media FILE still needs one runtime call —
`POST /api/_action/media/<id>/upload?extension=…&fileName=…` — AFTER the (seeded) media row exists;
seed everything else.

**Storefront CMS-block specs — reach the block via a CATEGORY page.** For a bug in a CMS
element (product slider, image, text) on the storefront, seed the block on a **category** and
navigate to its SEO URL (e.g. `await page.goto('/Repro-Category/')`) — categories render
reliably through the sales-channel navigation. **AVOID landing-page routes (`/landingPage/{id}`)
and raw CMS-page routes**: a landing page renders only when it is `active` AND assigned to the
sales channel, so it easily returns "Page not found" and the spec dies at
`PRECONDITION_NOT_FOUND` — a false negative (hit live on #32). Anchor the precondition on the
block's semantic REGION (its aria-label, e.g. a product slider's
`getByRole('region', {name:/Product gallery containing/i})`), never on a seeded product name.

**Cookie-consent-gated storefront features need their consent COOKIE set — the client-side
twin of the server-side `system_config` opt-in.** Several storefront features read/write
localStorage only after the shopper accepts their cookie; on a default install the consent
banner is shown and the cookie is absent, so the feature's JS returns early and the feature
looks dead even though you enabled it server-side. The **wishlist** gates on the
`wishlist-enabled` cookie (`local-wishlist.plugin.js`: `if (window.useDefaultCookieConsent &&
!CookieStorageHelper.getItem('wishlist-enabled')) return;`), so a guest-wishlist repro that
only seeds localStorage renders "Your wishlist is empty" (hit live on #33). Set the cookie in
the browser context BEFORE the feature's page loads:
```ts
await page.goto('/');                                   // establish the origin first
await page.context().addCookies([{ name: 'wishlist-enabled', value: '1', url: page.url() }]);
```
This complements (does NOT replace) the server-side `system_config: {core.cart.wishlistEnabled:
true}` — a gated feature needs BOTH the server flag and the browser consent cookie.

**File uploads — set files on the INPUT, never drive the native file chooser.** When a step
uploads a file (media library, Replace dialog, import), do NOT click an "Upload"/"Hochladen"
button and `page.waitForEvent('filechooser')`. Shopware's `sw-media-upload-v2` renders SEVERAL
controls (upload-file, upload-from-URL, a context menu), so a `getByRole('button',
{name:/upload/i}).first()` clicks the wrong one and NO native picker opens → `waitForEvent`
times out at `PRECONDITION_NOT_FOUND` (hit live on #29, "filechooser Timeout 10000ms exceeded").
Instead set files straight on the hidden file input, scoped to the dialog/region you're in:
```ts
await dialog.locator('input[type="file"]').setInputFiles({ name: 'f.png', mimeType: 'image/png', buffer });
```
The Locator method is **`setInputFiles`** — NOT `setFiles` (that exists only on the `FileChooser`
object, and calling it on a Locator throws `TypeError: …setFiles is not a function`, hit live on
#29). `setInputFiles` REQUIRES the `<input type=file>` element and a file input carries no
accessible name, so `input[type="file"]` is the one attribute selector this skill permits — it
is semantic (the element's PURPOSE, not a brittle CSS class) and is the standard Playwright
upload pattern. Scope it inside the relevant landmark (`dialog`/`region`) so it can't match a
stray uploader elsewhere on the page.

## Locators — version-stable, semantic ONLY
- Use `getByRole(role, {name})` / `getByLabel` / `getByText` / `getByPlaceholder` with
  case-insensitive regex and accessible/visible names. `getByRole` and `getByLabel` both
  resolve `aria-label` / `aria-labelledby` / associated `<label>` — Shopware's `mt-*`
  fields expose their label as the accessible name, so both work for inputs.
- **Scope inside the relevant landmark + use SPECIFIC names** to avoid strict-mode
  ambiguity: `getByRole('navigation').getByRole('link', {name:/^Products$/i})`, NOT a broad
  regex with `.first()`.
- **Anchor names and pin the role** — a broad regex matches sibling controls and throws a
  strict-mode violation. Real example: `getByLabel(/password/i)` matched the field, the
  "Show password" toggle, AND the "Forgot your password?" link. Use `/^password$/i` and pin
  the role: `getByRole('textbox', {name:/^password$/i})` (a password input is a `textbox`;
  the toggle is a `button`), so only the field matches.
- **A broad regex + `.first()` fails SILENTLY when it matches a SEEDED entity whose name
  contains the action word — anchor the action name with `^…$`.** Unlike a strict-mode
  violation (which throws), `.first()` just clicks the wrong element and the spec dies later at
  a misleading `PRECONDITION_NOT_FOUND`. Live miss on #29: the spec wanted the media sidebar's
  **Replace** action, but `getByRole('button', {name:/ersetzen|replace/i}).first()` matched the
  seeded media card `button "repro-replace-media …"` FIRST (its filename contains "re**place**"),
  re-selected the item instead of opening the dialog, and failed at "Replace dialog did not
  open". Use an EXACT-anchored name for action controls: `getByRole('button',
  {name:/^(ersetzen|replace)$/i})` — it cannot match the card. Corollary: do NOT give a seeded
  fixture a name that contains a UI action word (`replace`/`delete`/`save`/`upload`) you will
  also locate by.
- **Admin item actions often live behind a per-row context menu, not a visible button.** In
  list/grid views (media, products, categories) the row's actions ("Replace", "Delete",
  "Duplicate") are revealed by clicking the row's **"Open context menu"** button first, then
  clicking the action as a `menuitem`. If an action button isn't directly visible after
  selecting a row, open that context menu rather than declaring `PRECONDITION_NOT_FOUND`.
- **Beware untranslated snippet keys.** If the target admin renders raw keys (e.g.
  `global.sw-admin-menu.navigation.label` instead of "Navigation"), accessible-name / text
  locators can't match. Prefer the issue's own visible strings + structural roles; a
  name-not-found is a `PRECONDITION_NOT_FOUND` (→ inconclusive), never the symptom.
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
- **Anchor the precondition on an element you KNOW renders once the target state is reached —
  ideally the assertion's OWN target (or its container/landmark) — NOT a guessed auxiliary
  control.** Page chrome like a smart-bar "Back"/"Save" button, a breadcrumb, or a tab label
  drifts across versions and yields a FALSE `PRECONDITION_NOT_FOUND` when the page actually
  loaded fine. Live miss on #31: the CMS-detail editor and the target Settings button rendered,
  but the precondition waited for a "Back" button that this version shows as an X/close icon →
  inconclusive despite a perfectly loaded editor. Rule: if your assertion inspects element X,
  gate the precondition on X (or its container) — X absent then legitimately means cross-version
  drift; X present means proceed straight to the real assertion.

**(2) Symptom** — exactly ONE `await expect(...)` of the HEALTHY behaviour, with a generous
timeout. This is the ONLY failure that may mean `reproduced`.
- **Assert the symptom's ACTUAL mechanism, never an invented numeric threshold.** A made-up
  cutoff (`width > 39px`, `>= 3 items`, `height < 100`) is not the symptom — it fires on
  perfectly healthy UI and yields a false `reproduced` on BOTH legs → false `live_bug`. Live
  miss on #28 ("quantity input cuts off the 2nd digit"): the spec demanded the input box be
  `> 39px`, but at a 390px viewport the box is ~30px and "10" is FULLY visible — not cut off —
  so both legs "failed" the bogus threshold. For **clipped / cut-off / truncated** content read
  the real overflow signal, not the box size:
  `const clipped = await el.evaluate(n => n.scrollWidth > n.clientWidth + 1); expect(clipped).toBe(false);`
  For a **"collapsed to ~0"** symptom assert a near-zero width explicitly (`expect(box.width).toBeLessThan(4)` is the SYMPTOM, so invert: healthy = NOT near-zero). Derive the
  property the bug actually manifests in from the issue / fix PR — do not guess a boundary.
- For a hidden / closed / collapsed / off-canvas symptom use
  `await expect(locator).not.toBeInViewport()` or assert explicit state
  (`toHaveAttribute('aria-hidden','true')`) — **NOT** `not.toBeVisible()`: an element moved
  off-screen via transform/translate is still "visible" to Playwright, so `toBeVisible`
  fails on BOTH versions and FAKES a reproduction.
- **Make the symptom's PRECONDITION actually hold.** If it only fires when content exceeds
  the visible area (overflow/cut-off/"cannot scroll" bugs), FORCE a viewport that guarantees
  it (`test.use({ viewport: { width: 1280, height: 500 } })`) — at the default 720p the
  content may simply fit and the spec passes on the BUGGY version (a silent false negative
  we hit live: a 17-entry dropdown fit the default viewport).
- **VERIFY the reported TRIGGER is actually in effect before judging — an unestablished
  trigger is `PRECONDITION_NOT_FOUND` (inconclusive), NEVER a silent `not_reproduced`.** If the
  symptom needs seeded content to manifest (a wide image that overflows, N rows present, a
  specific element rendered), MEASURE that the condition holds first — never assume the fixture
  took. Live miss on #31: the seeded CMS text block + its 3000px `<img>` never rendered in the
  admin editor (the canvas was empty), so nothing overflowed, the Settings tab stayed put, and
  the spec returned `not_reproducible` — a false negative that hid that the reported steps
  ("add a wide image") were never exercised. Gate on the trigger, e.g.
  `const overflow = await canvas.evaluate(n => n.scrollWidth > n.clientWidth); if (!overflow) throw new Error('PRECONDITION_NOT_FOUND: wide image did not overflow the editor — trigger not established');`
  BEFORE the symptom `expect`. A verdict is trustworthy only if the reported condition was
  provably present.
- **For "cannot scroll / cannot reach" symptoms, scroll like a USER** — hover the element's
  container, then `page.mouse.wheel(0, N)`, then assert `toBeInViewport()`. **NEVER**
  `scrollIntoViewIfNeeded()` for these: it uses CDP and can scroll `overflow:hidden`
  ancestors a real user cannot, masking the bug (hit live: it scrolled the admin's
  `overflow:hidden` layout wrapper and faked a pass on the buggy version).
- When the target is one of many same-role items (rows, options), scope by each item's own
  visible text (`getByRole('row', {name:/module.?filter/i})`) before `.first()`/`.last()` —
  a bare role can match unrelated tables elsewhere on the page.

## How `run-playwright.sh` classifies the result
- genuine `expect()` assertion failure → `reproduced`
- `PRECONDITION_NOT_FOUND` / `test.skip`, navigation/connection error, or a non-assertion
  locator/timeout failure → `inconclusive` (cross-version UI drift, never a bogus
  `reproduced`)
- all pass → `not_reproduced`; no tests collected / unparseable → `inconclusive` / `blocked`

Evidence (screenshot, video, trace) is captured automatically under `test-results/`.
