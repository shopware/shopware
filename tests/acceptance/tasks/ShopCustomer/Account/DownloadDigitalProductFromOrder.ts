import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const DownloadDigitalProductFromOrderAndExpectContentToBe = base.extend<
    { DownloadDigitalProductFromOrderAndExpectContentToBe: Task },
    FixtureTypes
>({
    DownloadDigitalProductFromOrderAndExpectContentToBe: async ({ ShopCustomer, StorefrontAccountOrder }, use) => {
        const task = (contentOfFile: string) => {
            return async function DownloadDigitalProductFromOrder() {
                // On 6.8 the download grant runs after the paid request, so refresh the order
                // details until the Download link appears.
                await ShopCustomer.expects(async () => {
                    await StorefrontAccountOrder.page.reload();
                    await ShopCustomer.expects(StorefrontAccountOrder.orderExpandButton).toBeVisible();
                    await ShopCustomer.presses(StorefrontAccountOrder.orderExpandButton);
                    await ShopCustomer.expects(StorefrontAccountOrder.digitalProductDownloadButton).toBeVisible();
                }).toPass({ intervals: [500], timeout: 30_000 });

                const [newTab] = await Promise.all([
                    StorefrontAccountOrder.page.waitForEvent('popup'),
                    ShopCustomer.presses(StorefrontAccountOrder.digitalProductDownloadButton),
                ]);
                ShopCustomer.expects(await newTab.content()).toContain(contentOfFile);
            };
        };

        await use(task);
    },
});
