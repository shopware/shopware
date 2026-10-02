# Narrating a recorded repro (`record_video: true`)

**Read this whenever your `reproduction-plan.json` sets `record_video: true`.** You record video when
the symptom only reads *in motion* — an animation or transition, a drag, a hover/toggle, scrolling, a
loading/timing sequence, or "clicking X does nothing / does the wrong thing". A silent motion clip is
hard to follow, and the whole point of the video is that a human can watch the symptom happen — so
**narrate every recorded spec**. (A static rendering/text bug doesn't record video, so none of this
applies to it.)

Use the two helpers from `./video-helpers.js` (the harness places this file next to your spec at run
time — keep the import path exactly as written): `narrate(page, "what's happening")` (a subtitle) and
`mark(page, locator, "label")` (highlights the element about to be used).

Two rules keep the captions useful:

1. **A marker labels the action, not the element.** The highlight already shows *which* element, so
   write the thing you're doing — `mark(el, 'Click the Dashboard entry')` — never a label that just
   re-names what's highlighted (`'Dashboard nav link'` is noise).
2. **End with a `narrate` that says what a healthy shop should show.** The clip should close on the
   *expected outcome* — `narrate(page, 'the sidebar should close')` — so the viewer knows what to watch
   the buggy version fail. State it plainly.

So a walkthrough reads as: navigation/context via `narrate`, each interaction via `mark('<action>')`,
and a closing `narrate('<what should happen>')` — that last line, never a marker, is what stays on
screen at the end. Intermediate clicks are just their action captions; only the end states the
outcome. **Write each as its own single-line `await` statement, next to — never wrapping — the real
action**, e.g.:

```ts
import { test, expect } from '@playwright/test';
import { narrate, mark } from './video-helpers.js';   // stripped from the verdict run + the comment

test('admin sidebar closes after clicking a menu entry', async ({ page }) => {
  await narrate(page, 'Open the admin menu');
  await page.goto('/admin');

  const entry = page.getByRole('link', { name: 'Dashboard' });
  await mark(page, entry, 'Click the Dashboard entry');   // marker = the action; the highlight shows which
  await entry.click();

  await narrate(page, 'the sidebar should close');        // closing caption = what to watch; ends the clip
  await expect(page.getByRole('navigation', { name: 'Admin menu' })).toBeHidden();
});
```

The verdict run and the code shown in the comment are this spec with the `narrate`/`mark` lines and
the import **removed** — the actions and the single assertion are byte-identical. So narration only
ever affects the video, never the result. Don't add extra assertions or locators just to narrate.
