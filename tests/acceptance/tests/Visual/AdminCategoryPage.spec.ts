import { test, expect } from '@fixtures/AcceptanceTest';
import path from 'path';

test('Visual: Administration category page', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminCategories,
}) => {

    await test.step('Creates a screenshot of the category page on the general tab.', async () => {

        await ShopAdmin.goesTo(AdminCategories.url());
        await AdminCategories.page.locator('.sw-category-tree').locator('.tree-link').getByText('Home').click();
        await ShopAdmin.expects(AdminCategories.page.locator('.sw-media-upload-v2').locator('.sw-context-button__button')).toBeVisible();

        await AdminCategories.page.setViewportSize({ width: 1440, height: 2048});

        await expect(AdminCategories.page).toHaveScreenshot({
            stylePath: path.resolve('./tests/Visual/screenshot.css'),
            mask: [
                AdminCategories.page.locator('.sw-admin-menu__user-name'),
                AdminCategories.page.locator('.sw-avatar'),
            ],
        });
    });
});
