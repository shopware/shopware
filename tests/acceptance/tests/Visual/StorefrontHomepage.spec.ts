import { test, expect } from '@fixtures/AcceptanceTest';

test('Creates a screenshot of the Storefront Homepage.', { tag: '@Visual' }, async ({
    ShopCustomer,
    StorefrontHome,
    Login,
    ReplaceElementsForScreenshot,
}) => {
    await ShopCustomer.attemptsTo(Login());
    await ShopCustomer.goesTo(StorefrontHome.url());

    await test.step('Creates a screenshot and compare it on homepage in storefront.', async () => {

        ReplaceElementsForScreenshot(StorefrontHome.page, [
            '.cms-element-text h1',
        ]);

        await expect(StorefrontHome.page).toHaveScreenshot({
            fullPage: true,
        });   
    });
});