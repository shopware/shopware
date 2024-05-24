import { test as base, expect } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const DownloadDigitalProductFromOrderAndExpectContentToBe = base.extend<{ DownloadDigitalProductFromOrderAndExpectContentToBe: Task }, FixtureTypes>({
    DownloadDigitalProductFromOrderAndExpectContentToBe: async ({ StorefrontAccountOrder }, use)=> {
        const task = (contentOfFile: string) => {
            return async function DownloadDigitalProductFromOrder() {
                await StorefrontAccountOrder.orderExpandButton.click();

                const [newTab] = await Promise.all([
                    StorefrontAccountOrder.page.waitForEvent('popup'),
                    await StorefrontAccountOrder.digitalProductDownloadButton.click(),
                ]);
                const tabContent = await newTab.content();
                expect(tabContent).toContain(contentOfFile);
            }
        };

        await use(task);
    },
});
