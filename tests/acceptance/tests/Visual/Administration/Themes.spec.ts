import { test } from '@fixtures/AcceptanceTest';
import { assertScreenshot, setViewport } from '@shopware-ag/acceptance-test-suite';

test('Visual: Administration themes page', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminThemesListing,
    AdminThemesDetail,
}) => {

    await test.step('Creates a screenshot of the themes page.', async () => {
        await ShopAdmin.goesTo(AdminThemesListing.url());
        await setViewport(AdminThemesListing.page, {
            waitForSelector: AdminThemesListing.installedTheme('Shopware default theme'),
        });
        await assertScreenshot(AdminThemesListing.page, 'Themes-Listing.png');
    });

    await test.step('Creates a screenshot of the themes page.', async () => {
        await AdminThemesListing.installedTheme('Shopware default theme').click();
        await ShopAdmin.expects(AdminThemesDetail.themeCard('Theme colours')).toBeVisible();
        await AdminThemesDetail.sidebarButton.click();
        await setViewport(AdminThemesDetail.page, {
            width: 1600,
            scrollableElementVertical: AdminThemesDetail.scrollableElement,
            waitForSelector: AdminThemesDetail.themeCard('Theme colours'),
        });
        await assertScreenshot(AdminThemesDetail.page, 'Themes-Detail.png', AdminThemesDetail.contentView);
    });
});
