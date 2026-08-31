import type { FixtureTypes } from '@fixtures/AcceptanceTest';
import { chromium, test as base, type Page } from '@playwright/test';
import { playAudit } from 'playwright-lighthouse';

type ValidateLighthouseScoreType = (
    page: Page,
    name: string,
    thresholds?: {
        performance: number;
        accessibility: number;
        'best-practices': number;
        seo: number;
    },
) => () => Promise<void>;

export const ValidateLighthouseScore = base.extend<{ ValidateLighthouseScore: ValidateLighthouseScoreType }, FixtureTypes>({
    ValidateLighthouseScore: async ({}, use, testInfo) => {
        const task: ValidateLighthouseScoreType = (
            page: Page,
            name: string,
            thresholds = {
                performance: 50,
                accessibility: 100,
                'best-practices': 50,
                seo: 30,
            },
        ) => {
            return async function ValidateLighthouseScore() {
                /**
                 * Lighthouse audits the URL in its own tab via the Chrome debugging port.
                 * Launch a dedicated browser with a worker-unique port for the audit, so the
                 * regular test browsers don't have to bind a fixed port — a fixed port would
                 * collide as soon as tests run with more than one worker.
                 */
                const port = 9222 + testInfo.parallelIndex;
                const browser = await chromium.launch({ args: [`--remote-debugging-port=${port}`] });

                try {
                    await playAudit({
                        url: page.url(),
                        port,
                        thresholds,
                        reports: {
                            formats: {
                                html: true,
                            },
                            name,
                            directory: 'test-results/lighthouse',
                        },
                    });
                } finally {
                    await browser.close();
                }
            };
        };

        await use(task);
    },
});
