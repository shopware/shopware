import { test, expect } from '@fixtures/AcceptanceTest';
import path from 'path';

test('Visual: Homepage in the Storefront.', { tag: '@Visual' }, async ({
    ShopCustomer,
    StorefrontHome,
    Login,
}) => {
    await ShopCustomer.attemptsTo(Login());
    await ShopCustomer.goesTo(StorefrontHome.url());

    await StorefrontHome.page.setViewportSize({ width: 1280, height: 1111});


    await test.step('Creates a screenshot and compare it on homepage in storefront.', async () => {

        await expect(StorefrontHome.page).toHaveScreenshot({
            maxDiffPixelRatio: 0.2,
            fullPage: true,

        });
    });
});