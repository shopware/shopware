import { test, expect } from '@fixtures/AcceptanceTest';
import path from 'path';

test('Visual: Shipping listing page in administration', { tag: '@Visual' }, async ({
    TestDataService,
    ShopAdmin,
    AdminShippingListing,
    Login,
}) => {
    await TestDataService.createBasicShippingMethod();
    
    await ShopAdmin.attemptsTo(Login());
    await ShopAdmin.goesTo(AdminShippingListing.url());
    await ShopAdmin.expects(AdminShippingListing.header).toBeVisible();
    await ShopAdmin.expects(AdminShippingListing.contextMenu).toBeVisible();

    await test.step('Creates a screenshot and compare it on shipping listing page in admin.', async () => {

        await expect(AdminShippingListing.page).toHaveScreenshot({
            fullPage: true,
            stylePath: path.resolve('./tests/Visual/screenshot.css'),
        });
    });
});