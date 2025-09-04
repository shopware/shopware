import { test, expect } from '@fixtures/AcceptanceTest';
import { setViewport } from '@shopware-ag/acceptance-test-suite';

test('Visual: Administration themes page', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminThemesListing,
    AdminThemesDetail,
}) => {

    await test.step('Creates a screenshot of the themes page.', async () => {
        await ShopAdmin.goesTo(AdminThemesListing.url());
        await setViewport(AdminThemesListing.page, {
            waitForSelector: AdminThemesListing.installedThemes('Shopware default theme'),
        });
        await expect(AdminThemesListing.contentView).toHaveScreenshot('Themes-Listing.png');
    });

    await test.step('Creates a screenshot of the themes page.', async () => {
        await AdminThemesListing.installedThemes('Shopware default theme').click();
        await ShopAdmin.expects(AdminThemesDetail.themeCard('Theme colours')).toBeVisible();
        await AdminThemesDetail.sidebarButton.click();
        await setViewport(AdminThemesDetail.page, {
            width: 1600,
            scrollableElementVertical: '.sw-page__main-content-inner',
            waitForSelector: AdminThemesDetail.themeCard('Theme colours'),
        });
        await expect(AdminThemesDetail.contentView).toHaveScreenshot('Themes-Detail.png');
    });
});
