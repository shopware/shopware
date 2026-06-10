import { defineConfig } from '@playwright/test';

// Minimal config for a single generated repro spec. baseURL comes from the leg's
// running shop; trace/video/screenshot are captured as evidence (config-only options).
export default defineConfig({
    testDir: '.',
    outputDir: 'test-results',
    reporter: [['json', { outputFile: 'pw-report.json' }]],
    use: {
        baseURL: process.env['APP_URL'],
        trace: 'on',
        video: 'on',
        screenshot: 'on',
    },
});
