// Video-only helpers for the trunk narrated pass: on-screen subtitles (`narrate`) and a highlight
// ring on the element about to be used (`mark`). They render only in the video pass — the verdict
// run strips these calls out entirely (see strip-narration.mjs) — so they can never affect a result.
// Author them as standalone one-line `await` statements; the real action stays a separate plain line.
const DELAY = Number(process.env.REPRO_VIDEO_STEP_MS || 900);

/**
 * Shows a subtitle overlay during the optional narrated video pass.
 *
 * The verdict spec is stripped before execution, so this helper is evidence-only and must remain
 * safe to remove without changing the reproduction logic.
 */
export async function narrate(page, text) {
  await page.evaluate((message) => {
    let el = document.querySelector('[data-repro-subtitle]');
    if (!el) {
      el = document.createElement('div');
      el.setAttribute('data-repro-subtitle', '');
      Object.assign(el.style, {
        position: 'fixed', left: '50%', bottom: '24px', transform: 'translateX(-50%)', zIndex: '2147483647',
        maxWidth: 'min(900px, calc(100vw - 48px))', padding: '12px 16px', borderRadius: '6px',
        background: 'rgba(10,18,32,.92)', color: '#fff', font: '600 18px/1.35 Arial, sans-serif',
        textAlign: 'center', pointerEvents: 'none',
      });
      document.body.appendChild(el);
    }
    el.textContent = message;
  }, text).catch(() => {});
  await page.waitForTimeout(DELAY);
}

/**
 * Highlights the next interacted element during narrated video capture.
 *
 * The marker is transient visual evidence for reviewers; authored specs call it on its own line so
 * strip-narration can remove it cleanly for the machine verdict run.
 */
export async function mark(page, locator, label = '') {
  await locator.scrollIntoViewIfNeeded().catch(() => {});
  const box = await locator.boundingBox().catch(() => null);
  if (box) {
    await page.evaluate(({ x, y, w, h }) => {
      const ring = document.createElement('div');
      ring.setAttribute('data-repro-marker', '');
      Object.assign(ring.style, {
        position: 'fixed', left: `${x}px`, top: `${y}px`, width: `${w}px`, height: `${h}px`,
        border: '3px solid #ff3d00', borderRadius: '6px', boxShadow: '0 0 0 6px rgba(255,61,0,.22)',
        zIndex: '2147483647', pointerEvents: 'none',
      });
      document.body.appendChild(ring);
      setTimeout(() => ring.remove(), 1400);
    }, { x: box.x, y: box.y, w: box.width, h: box.height }).catch(() => {});
  }
  if (label) {
    await narrate(page, label);
  } else {
    await page.waitForTimeout(DELAY);
  }
}
