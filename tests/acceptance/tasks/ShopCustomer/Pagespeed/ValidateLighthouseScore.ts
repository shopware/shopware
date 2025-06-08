import { test as base, Page } from '@playwright/test';
import type { FixtureTypes } from '@fixtures/AcceptanceTest';
import { playAudit } from 'playwright-lighthouse';

type ValidateLighthouseScoreType = (
    page: Page,
    name: string,
    thresholds: {
        'performance': number,
        'accessibility': number,
        'best-practices': number,
        'seo': number,
    },
    port: number,
) => () => Promise<void>;

export const ValidateLighthouseScore = base.extend<{ ValidateLighthouseScore: ValidateLighthouseScoreType }, FixtureTypes>({
    ValidateLighthouseScore: async ({}, use)=> {
        const task: ValidateLighthouseScoreType = (
            page: Page,
            name: string,
            thresholds = {
                'performance': 50,
                'accessibility': 100,
                'best-practices': 50,
                'seo': 30,
            },
            port = 9222,
        ) => {
            return async function ValidateLighthouseScore() {
                await playAudit({
                    page,
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
            }
        };

        await use(task);
    },
});
