import { defineConfig } from '@playwright/test';
import { cwd } from 'node:process';

// Minimal config for a single generated repro spec. `testDir: '.'` is the config's OWN
// directory — run-playwright.sh copies the generated spec here so it is the only spec
// collected (a spec left at the workspace root is NOT discovered). baseURL comes from the
// leg's running shop; outputDir is pinned to the workspace root so the workflow uploads the
// trace/video/screenshot evidence.
export default defineConfig({
    testDir: '.',
    outputDir: `${cwd()}/test-results`,
    reporter: [['json', { outputFile: 'pw-report.json' }]],
    use: {
        baseURL: process.env['APP_URL'],
        trace: 'on',
        video: 'on',
        screenshot: 'on',
    },
});
