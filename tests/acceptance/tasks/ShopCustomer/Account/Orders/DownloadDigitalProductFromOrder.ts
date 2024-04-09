import { test as base, expect } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const DownloadDigitalProductFromOrderAndExpectContentToBe = base.extend<{ DownloadDigitalProductFromOrderAndExpectContentToBe: Task }, FixtureTypes>({
    DownloadDigitalProductFromOrderAndExpectContentToBe: async ({ accountOrderPage }, use)=> {
        const task = (contentOfFile: string) => {
            return async function DownloadDigitalProductFromOrder() {
                await accountOrderPage.orderExpandButton.click();
                
                const [newTab] = await Promise.all([
                    accountOrderPage.page.waitForEvent('popup'),
                    await accountOrderPage.digitalProductDownloadButton.click(),
                ]);
                const tabContent = await newTab.content();
                await expect(tabContent).toContain(contentOfFile);
            }
        };

        await use(task);
    },
});