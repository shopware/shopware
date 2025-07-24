import { test, expect } from '@fixtures/AcceptanceTest';
import { setViewport, replaceElements } from '@shopware-ag/acceptance-test-suite';

test('Visual: Administration category page', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminCategories,
}) => {

    await test.step('Creates a screenshot of the category page on the general tab.', async () => {
        await ShopAdmin.goesTo(AdminCategories.url());
        await AdminCategories.categoryItems.first().click();
        await AdminCategories.page.waitForLoadState('load');
        await setViewport(AdminCategories.page, {
            requestURL: 'api/search/category',
        });
        await replaceElements(AdminCategories.page, [
            AdminCategories.categoryItems,
        ]);
        await expect(AdminCategories.contentView).toHaveScreenshot();
    });

    await test.step('Creates a screenshot of the "configure home page" modal.', async () => {
        await AdminCategories.configureHomePageButton.click();
        await AdminCategories.page.waitForLoadState('load');
        await setViewport(AdminCategories.page, {
            requestURL: 'api/search/category',
        });
        await expect(AdminCategories.contentView).toHaveScreenshot();
        await AdminCategories.configureModalCancelButton.click();
    });

    await test.step('Creates a screenshot of the category page on the products tab.', async () => {
        await AdminCategories.productsTab.click();
        await AdminCategories.page.waitForLoadState('load');
        await setViewport(AdminCategories.page, {
            requestURL: 'api/search/category',
        });
        await replaceElements(AdminCategories.page, [
            AdminCategories.categoryItems,
        ]);
        await expect(AdminCategories.contentView).toHaveScreenshot();
    });

    await test.step('Creates a screenshot of the category page on the layout tab.', async () => {
        await AdminCategories.layoutTab.click();
        await AdminCategories.page.waitForLoadState('load');
        await setViewport(AdminCategories.page, {
            requestURL: 'api/search/category',
        });
        await replaceElements(AdminCategories.page, [
            AdminCategories.categoryItems,
        ]);
        await expect(AdminCategories.contentView).toHaveScreenshot();
    });

    await test.step('Creates a screenshot of the category page on the SEO tab.', async () => {
        await AdminCategories.seoTab.click();
        await AdminCategories.page.waitForLoadState('load');
        await setViewport(AdminCategories.page, {
            requestURL: 'api/search/category',
        });
        await replaceElements(AdminCategories.page, [
            AdminCategories.categoryItems,
        ]);
        await expect(AdminCategories.contentView).toHaveScreenshot();
    });
});
