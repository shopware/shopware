import { defineConfig } from '@playwright/test';
import { cwd } from 'node:process';

// Config for a single generated repro spec. The playwright executor copies the spec next to this
// file and runs it, so `testDir: '.'` collects exactly that one spec. baseURL is the leg's running
// shop; the json report drives the machine verdict, the html report is uploaded for humans.
// PW_VIDEO is set per-run by the executor: 'off' for the verdict run, 'on' for the separate trunk
// video pass (which also slows actions down so the recording is followable).
const video = process.env['PW_VIDEO'] === 'on';

// PW_VIEWPORT (JSON `{width,height}`) comes from the plan's `viewport`. It MUST be applied at context
// creation — the video recorder fixes its frame size here — so a mobile/responsive repro records at
// the right dimensions. Resizing later with page.setViewportSize() paints the smaller page inside the
// original (desktop) frame, leaving a grey border. Absent ⇒ Playwright's default desktop viewport.
const viewport = process.env['PW_VIEWPORT'] ? JSON.parse(process.env['PW_VIEWPORT']) : null;

export default defineConfig({
  testDir: '.',
  testIgnore: ['**/demo/**'],
  timeout: 120_000, // a repro is a multi-step flow on a fresh shop; 30s default aborts mid-flow
  outputDir: process.env['PW_OUTPUT_DIR'] || `${cwd()}/test-results`,
  reporter: [
    ['json', { outputFile: process.env['PW_JSON_REPORT'] || `${cwd()}/pw-report.json` }],
    ['html', { outputFolder: process.env['PW_HTML_REPORT'] || `${cwd()}/playwright-report`, open: 'never' }],
  ],
  use: {
    baseURL: process.env['APP_URL'],
    // Bound per-action/navigation waits. Without this an action (e.g. a click on an element that
    // never becomes actionable — common when the trunk UI diverges from the buggy version) waits the
    // whole 120s test budget, yielding a vague timeout. A 15s cap fails fast with a precise "waiting
    // for element … " so the classifier can name the missing state instead of just "timed out".
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
    // Admin specs start authenticated (login-state.mjs); storefront specs start consented
    // (consent-state.mjs). Either is passed here so specs never author their own auth.
    storageState: process.env['PW_STORAGE'] || undefined,
    ...(viewport ? { viewport } : {}),
    // The narrated video pass discards its trace/screenshots (only video.webm is copied out), so skip
    // recording them there; the verdict run keeps both for human review of the official result.
    trace: video ? 'off' : 'on',
    video: video ? (viewport ? { mode: 'on', size: viewport } : 'on') : 'off',
    screenshot: video ? 'off' : 'on',
    launchOptions: { slowMo: video ? Number(process.env['REPRO_VIDEO_SLOWMO'] || 400) : 0 },
  },
});
