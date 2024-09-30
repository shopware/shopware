import { test, expect } from '@fixtures/AcceptanceTest';
import path from 'path';

test('Visual: Homepage in the Storefront.', { tag: '@Visual' }, async ({
    ShopCustomer,
    StorefrontHome,
    Login,
}) => {
    await ShopCustomer.attemptsTo(Login());
    await ShopCustomer.goesTo(StorefrontHome.url());

    await test.step('Creates a screenshot and compare it on homepage in storefront.', async () => {

        await expect(StorefrontHome.page).toHaveScreenshot({
            fullPage: true,
            stylePath: path.resolve('./tests/Visual/screenshot.css'),

        });
    });
});