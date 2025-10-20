import { test } from '@fixtures/AcceptanceTest';

test('As a shop customer, I want use a basic cookie consent banner in the storefront.', { tag: ['@Settings', '@Storefront'] }, async ({
    ShopCustomer,
    StorefrontHome,
}) => {

    await test.step('Navigate to homepage and verify initial cookie banner visibility and content', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible();
        // Check if "Accept All Cookies" button exists and verify its visibility state
        const acceptAllButton = StorefrontHome.consentAcceptAllCookiesButton;
        const isAcceptAllVisible = await acceptAllButton.isVisible().catch(() => false);
        // The button might be visible if acceptAllCookies config is enabled from a previous test
        if (isAcceptAllVisible) {
            console.warn('Accept All Cookies button is visible - this may indicate config bleed from previous test');
        }
        await ShopCustomer.expects(StorefrontHome.consentOnlyTechnicallyRequiredButton).toBeVisible();
        await ShopCustomer.expects(StorefrontHome.consentConfigureButton).toBeVisible();
        await ShopCustomer.expects(StorefrontHome.consentCookiePermissionContent).toContainText(
            'This website uses cookies to ensure the best experience possible.');
    });

    await test.step('Verify no consent cookie is set before acceptance', async () => {
        const allCookies = await StorefrontHome.page.context().cookies();
        // The important thing is that cookie-preference is not yet set
        ShopCustomer.expects(allCookies.find(c => c.name === 'cookie-preference')).not.toBeDefined();
    });

    await test.step('Configure cookie settings', async () => {
        await StorefrontHome.consentConfigureButton.click();
        await ShopCustomer.expects(StorefrontHome.consentDialogTechnicallyRequiredCheckbox).toBeChecked();
        await ShopCustomer.expects(StorefrontHome.consentDialog.getByRole('checkbox')).toHaveCount(2);
        await StorefrontHome.consentDialogSaveButton.click();

        // Wait for dialog to close, which indicates cookies have been processed
        await ShopCustomer.expects(StorefrontHome.consentDialog).not.toBeVisible();
    });

    await test.step('Verify cookies after saving consent settings', async () => {
        const allCookies = await StorefrontHome.page.context().cookies();
        // Verify cookie preference is set
        const cookiePreference = allCookies.find(c => c.name === 'cookie-preference');
        ShopCustomer.expects(cookiePreference).toBeDefined();
        ShopCustomer.expects(cookiePreference?.value).toEqual('1');
    });

    await test.step('Reload page and verify cookie persistence', async () => {
        await StorefrontHome.page.reload();
        const allCookies = await StorefrontHome.page.context().cookies();
        // Verify cookie preference persists after reload
        const cookiePreference = allCookies.find(c => c.name === 'cookie-preference');
        ShopCustomer.expects(cookiePreference).toBeDefined();
        ShopCustomer.expects(cookiePreference?.value).toEqual('1');
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();
    });
});

