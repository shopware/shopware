import { test, expect } from '@fixtures/AcceptanceTest';
import path from 'path';

test('Visual: Administration category page', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminCategories,
    ReplaceElementsForScreenshot,
}) => {

    await test.step('Creates a screenshot of the category page on the general tab.', async () => {
        await ShopAdmin.goesTo(AdminCategories.url());
        await AdminCategories.page.locator('.sw-category-tree').locator('.tree-link').first().click();
        await AdminCategories.page.waitForLoadState('load');
        await AdminCategories.page.setViewportSize({ width: 1440, height: 2200});
        await ReplaceElementsForScreenshot(AdminCategories.page, [
            '.tree-link',
        ]);
        await expect(AdminCategories.page.locator('.sw-desktop__content')).toHaveScreenshot();
    });

    await test.step('Creates a screenshot of the "configure home page" modal.', async () => {
        await AdminCategories.page.getByRole('button', { name: 'Configure home page' }).click();
        await AdminCategories.page.waitForLoadState('load');
        await AdminCategories.page.setViewportSize({ width: 1440, height: 1440});
        await expect(AdminCategories.page.locator('.sw-desktop__content')).toHaveScreenshot();
        await AdminCategories.page.getByLabel('Configure home page').getByRole('button', { name: 'Cancel' }).click();
    });

    await test.step('Creates a screenshot of the category page on the products tab.', async () => {
        await AdminCategories.page.locator('.sw-tabs__content').getByText('Products').click();
        await AdminCategories.page.waitForLoadState('load');
        await AdminCategories.page.setViewportSize({ width: 1440, height: 1280});
        await ReplaceElementsForScreenshot(AdminCategories.page, [
            '.tree-link',
        ]);
        await expect(AdminCategories.page.locator('.sw-desktop__content')).toHaveScreenshot();
    });

    await test.step('Creates a screenshot of the category page on the layout tab.', async () => {
        await AdminCategories.page.locator('.sw-tabs__content').getByText('Layout').click();
        await AdminCategories.page.waitForLoadState('load');
        await AdminCategories.page.setViewportSize({ width: 1440, height: 3660});
        await ReplaceElementsForScreenshot(AdminCategories.page, [
            '.tree-link',
        ]);
        await expect(AdminCategories.page.locator('.sw-desktop__content')).toHaveScreenshot();
    });

    await test.step('Creates a screenshot of the category page on the SEO tab.', async () => {
        await AdminCategories.page.locator('.sw-tabs__content').getByText('SEO').click();
        await AdminCategories.page.waitForLoadState('load');
        await AdminCategories.page.setViewportSize({ width: 1440, height: 1280});
        await ReplaceElementsForScreenshot(AdminCategories.page, [
            '.tree-link',
        ]);
        await expect(AdminCategories.page.locator('.sw-desktop__content')).toHaveScreenshot();
    });
});
