import { test, expect } from '@fixtures/AcceptanceTest';
import { replaceElements } from '@shopware-ag/acceptance-test-suite';

test('Creates a screenshot of the Storefront Homepage.', { tag: '@Visual' }, async ({
    ShopCustomer,
    StorefrontHome,
    Login,
}) => {
    await test.step('Creates a screenshot and compare it on homepage in storefront.', async () => {
        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontHome.url());
        await replaceElements(StorefrontHome.page, [
            StorefrontHome.categoryTitle,
        ]);
        await expect(StorefrontHome.page).toHaveScreenshot('Homepage.png', {
            fullPage: true,
        });
    });
});
