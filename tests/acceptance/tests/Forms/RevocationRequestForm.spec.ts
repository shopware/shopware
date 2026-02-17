import { expect, test } from '@fixtures/AcceptanceTest';

test(
    'As a merchant, I want to switch on and off the revocation request form',
    { tag: ['@Form', '@Revocation', '@Storefront'] },
    async ({ ShopCustomer, ShopAdmin, AdminDashboard, AdminSettingsListing, StorefrontHome, TestDataService, DefaultSalesChannel }) => {
        test.slow();

        await test.step('Visit the home page to check there is no revocation button', async () => {

            // await TestDataService.setSystemConfig({ 'core.basicInformation.showRevocationButton': false });
            await ShopCustomer.goesTo(StorefrontHome.url());
            const revocationFormButton = StorefrontHome.page.getByText('Revoke a Contract');
            await expect(revocationFormButton).toBeHidden();
        });

        await test.step('Enables the revocation button and check if it is visible', async () => {
            await ShopAdmin.goesTo(AdminSettingsListing.url() + '/basic/information/index')
            await AdminDashboard.page.getByText('Show "Revoke a Contract" button in footer').click();
            await AdminDashboard.page.getByText('Save').click();

            await TestDataService.setSystemConfig({ 'core.basicInformation.showRevocationButton': true });
            await ShopCustomer.goesTo(StorefrontHome.url());
            const revocationFormButton = StorefrontHome.page.getByText('Revoke a Contract');
            await expect(revocationFormButton).toBeVisible();
        });
    }
);