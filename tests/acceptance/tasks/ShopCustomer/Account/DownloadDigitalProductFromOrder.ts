import { test as base, expect } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const DownloadDigitalProductFromOrderAndExpectContentToBe = base.extend<{ DownloadDigitalProductFromOrderAndExpectContentToBe: Task }, FixtureTypes>({
    DownloadDigitalProductFromOrderAndExpectContentToBe: async ({ ShopCustomer, StorefrontAccountOrder }, use)=> {
        const task = (contentOfFile: string) => {
            return async function DownloadDigitalProductFromOrder() {
                // TODO: Migrate to StorefrontAccountOrder.orderExpandButton.click(); when https://github.com/shopware/acceptance-test-suite/pull/126 is released.
                await ShopCustomer.presses(StorefrontAccountOrder.page.locator('.order-hide-btn').first());

                const [newTab] = await Promise.all([
                    StorefrontAccountOrder.page.waitForEvent('popup'),
                    await ShopCustomer.presses(StorefrontAccountOrder.digitalProductDownloadButton),
                ]);
                const tabContent = await newTab.content();
                expect(tabContent).toContain(contentOfFile);
            }
        };

        await use(task);
    },
});
