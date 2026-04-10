import type { FixtureTypes } from '@fixtures/AcceptanceTest';
import { test as base } from '@playwright/test';

interface AcceptTechnicalRequiredCookies {
    acceptTechnicalRequiredCookies: () => Promise<void>;
}

export const AcceptTechnicalRequiredCookies = base.extend<AcceptTechnicalRequiredCookies, FixtureTypes>({
    acceptTechnicalRequiredCookies: async ({ StorefrontHome, ShopCustomer }, use) => {
        const acceptTechnicalRequiredCookies = async () => {
            const cookiePermissionButton = StorefrontHome.page.getByRole('button', { name: 'Only technically required' });
            await ShopCustomer.expects(cookiePermissionButton).toBeVisible();
            await ShopCustomer.presses(cookiePermissionButton);
            await ShopCustomer.expects(StorefrontHome.page.getByRole('region', { name: 'Cookie preferences' })).not.toBeVisible();
        };
        await use(acceptTechnicalRequiredCookies);
    },
});
