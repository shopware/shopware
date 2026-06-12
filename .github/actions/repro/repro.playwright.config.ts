import { defineConfig } from '@playwright/test';
import { cwd } from 'node:process';

// Minimal config for a single generated repro spec. `testDir: '.'` is the config's OWN
// directory — run-playwright.sh copies the generated spec here so it is the only spec
// collected (a spec left at the workspace root is NOT discovered). baseURL comes from the
// leg's running shop; outputDir is pinned to the workspace root so the workflow uploads the
// trace/video/screenshot evidence. testIgnore excludes the committed dry-run demo fixtures
// under demo/ — testDir recurses, so without this a real run would ALSO collect demo/.
export default defineConfig({
    testDir: '.',
    testIgnore: '**/demo/**',
    // A repro is a multi-step flow against a freshly-provisioned shop (login + navigate +
    // interact), with generous per-locator waits. Playwright's 30s default per-test timeout
    // is too short and aborts mid-flow; give the whole test room.
    timeout: 120_000,
    outputDir: `${cwd()}/test-results`,
    // json → machine verdict (run-playwright.sh reads it); html → the rich interactive
    // report (trace/screenshots/steps) uploaded with the leg so a human can open it.
    // open:'never' so CI never tries to launch a browser to serve it.
    reporter: [
        ['json', { outputFile: `${cwd()}/pw-report.json` }],
        ['html', { outputFolder: `${cwd()}/playwright-report`, open: 'never' }],
    ],
    use: {
        baseURL: process.env['APP_URL'],
        // For admin-ui repros the executor pre-authenticates (bin/login-state.mjs) and
        // passes the saved session here — generated specs start logged in and must not
        // author their own login steps (the recurring source of locator fumbles).
        storageState: process.env['PW_STORAGE'] || undefined,
        trace: 'on',
        video: 'on',
        screenshot: 'on',
    },
});
