import type { FixtureTypes } from '@fixtures/AcceptanceTest';
import { test as base } from '@playwright/test';

type AcceptTechnicalRequiredCookies = {
    acceptTechnicalRequiredCookies: (page?: { page: { locator: (selector: string) => { click: () => Promise<void>; } } }) => Promise<void>;
};

export const AcceptTechnicalRequiredCookies = base.extend<AcceptTechnicalRequiredCookies, FixtureTypes>({
    acceptTechnicalRequiredCookies: async ({ StorefrontHome, ShopCustomer }, use) => {
        const acceptTechnicalRequiredCookies = async (page = StorefrontHome) => {
            await base.step('Customer accept technical required cookies on the current page', async () => {
                const cookiePermissionButton = page.page.locator('.js-cookie-permission-button');
                await ShopCustomer.expects(cookiePermissionButton).toBeVisible();
                await cookiePermissionButton.click();
                await ShopCustomer.expects(page.page.locator('.cookie-permission-container')).not.toBeVisible();
            });
        };
        await use(acceptTechnicalRequiredCookies);
    },
});
