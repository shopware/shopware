import { test } from '@fixtures/AcceptanceTest';

test('As a shop customer, I want use a basic cookie consent banner in the storefront.', { tag: '@Settings' }, async ({
    ShopCustomer,
    StorefrontHome,
}) => {

    await test.step('Navigate to homepage and verify initial cookie banner visibility and content', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible();
        await ShopCustomer.expects(StorefrontHome.consentAcceptAllCookiesButton).not.toBeVisible();
        await ShopCustomer.expects(StorefrontHome.consentOnlyTechnicallyRequiredButton).toBeVisible();
        await ShopCustomer.expects(StorefrontHome.consentConfigureButton).toBeVisible();
        await ShopCustomer.expects(StorefrontHome.consentCookiePermissionContent).toContainText(
            'This website uses cookies to ensure the best experience possible.');
    });

    await test.step('Verify initial cookies before any consent', async () => {
        const allCookies = await StorefrontHome.page.context().cookies();
        ShopCustomer.expects(allCookies.length).toEqual(2);
    });

    await test.step('Configure cookie settings', async () => {
        await StorefrontHome.consentConfigureButton.click();
        await ShopCustomer.expects(StorefrontHome.consentDialogTechnicallyRequiredCheckbox).toBeChecked();
        await ShopCustomer.expects(StorefrontHome.consentDialog.getByRole('checkbox')).toHaveCount(2);
        await StorefrontHome.consentDialogSaveButton.click();
    });

    await test.step('Verify cookies after saving consent settings', async () => {
        const allCookies = await StorefrontHome.page.context().cookies();
        ShopCustomer.expects(allCookies.length).toEqual(3);
    });

    await test.step('Reload page and verify cookie persistence', async () => {
        await StorefrontHome.page.reload();
        const allCookies = await StorefrontHome.page.context().cookies();
        ShopCustomer.expects(allCookies.length).toEqual(3);
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();
    });
});

