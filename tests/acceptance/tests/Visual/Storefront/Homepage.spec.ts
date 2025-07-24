import { test, expect } from '@fixtures/AcceptanceTest';
import { VisualTestHelpers } from '@shopware-ag/acceptance-test-suite';

test('Creates a screenshot of the Storefront Homepage.', { tag: '@Visual' }, async ({
    ShopCustomer,
    StorefrontHome,
    Login,
}) => {
    await test.step('Creates a screenshot and compare it on homepage in storefront.', async () => {
        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontHome.url());
        await VisualTestHelpers.setViewport(StorefrontHome.page, {
            //waitForSelector: '.cms-element-product-listing',
        });
        VisualTestHelpers.replaceElements(StorefrontHome.page, [
            StorefrontHome.categoryTitle,
        ]);
        await ShopCustomer.expects(StorefrontHome.page).toHaveScreenshot('Homepage.png', {
            fullPage: true,
        });
    });
});
