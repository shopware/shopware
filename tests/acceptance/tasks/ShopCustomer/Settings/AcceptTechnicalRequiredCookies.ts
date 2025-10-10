import type { FixtureTypes } from '@fixtures/AcceptanceTest';
import { test as base } from '@playwright/test';

interface AcceptTechnicalRequiredCookies {
    acceptTechnicalRequiredCookies: () => Promise<void>;
}

export const AcceptTechnicalRequiredCookies = base.extend<AcceptTechnicalRequiredCookies, FixtureTypes>({
    acceptTechnicalRequiredCookies: async ({ StorefrontHome, ShopCustomer }, use) => {
        const acceptTechnicalRequiredCookies = async () => {
            const cookiePermissionButton = StorefrontHome.page.locator('.js-cookie-permission-button');
            await ShopCustomer.expects(cookiePermissionButton).toBeVisible();
            await cookiePermissionButton.click();
            await ShopCustomer.expects(StorefrontHome.page.locator('.cookie-permission-container')).not.toBeVisible();
        };
        await use(acceptTechnicalRequiredCookies);
    },
});
