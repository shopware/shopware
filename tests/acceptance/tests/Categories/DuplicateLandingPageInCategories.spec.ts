import { test, expect } from '@fixtures/AcceptanceTest';

test(
    'Shop administrator should be able to duplicate a landing page as an inactive copy.',
    { tag: '@Categories' },
    async ({ ShopAdmin, IdProvider, TestDataService, AdminApiContext, AdminCategories, DefaultSalesChannel }) => {
        const landingPage = await TestDataService.createLandingPage({
            name: `00_duplicatelandingpage_${IdProvider.getIdPair().uuid}`,
        });
        let cloneId: string;

        await test.step('Duplicate the landing page from the landing page tree.', async () => {
            await ShopAdmin.goesTo(AdminCategories.url());
            await AdminCategories.landingPageHeadline.click();

            const treeItem = AdminCategories.landingPageArea
                .locator('.sw-tree-item__element')
                .filter({ has: ShopAdmin.page.getByText(landingPage.name, { exact: true }) });
            await treeItem.locator('.sw-context-button__button').click();

            const cloneResponse = ShopAdmin.page.waitForResponse(`**/api/_action/clone/landing-page/${landingPage.id}`);
            await ShopAdmin.page.locator('.sw-context-menu__duplicate-action').click();

            const response = await cloneResponse;
            expect(response.ok()).toBeTruthy();
            cloneId = (await response.json()).id;
            TestDataService.addCreatedRecord('landing_page', cloneId);
        });

        await test.step('Verify the copy is inactive, keeps its assignments and shows up in the tree.', async () => {
            const response = await AdminApiContext.post('search/landing-page', {
                data: { ids: [cloneId], associations: { salesChannels: {} } },
            });
            expect(response.ok()).toBeTruthy();
            const [clone] = (await response.json()).data;

            expect(clone.active).toBe(false);
            expect(clone.name).not.toBe(landingPage.name);
            expect(clone.name.startsWith(`${landingPage.name} `)).toBe(true);
            expect(clone.url.startsWith(`${landingPage.url}-`)).toBe(true);
            expect(clone.salesChannels.map(({ id }) => id)).toEqual([DefaultSalesChannel.salesChannel.id]);

            await ShopAdmin.expects(await AdminCategories.getLandingPageByName(clone.name)).toBeVisible();
        });
    },
);
