import { expect, test } from '@fixtures/AcceptanceTest';

test(
    'As a merchant, I want to switch on and off the revocation request form',
    { tag: ['@Form', '@Revocation', '@Storefront'] },
    async ({ ShopCustomer, StorefrontHome, TestDataService }) => {
        test.slow();

        await test.step('Visit the home page to check there is no revocation button', async () => {
            await TestDataService.setSystemConfig({ 'core.basicInformation.showRevocationButton': false });
            await ShopCustomer.goesTo(StorefrontHome.url());
            const revocationFormButton = StorefrontHome.page.getByText(/Revoke a contract|Vertrag widerrufen/);
            await expect(revocationFormButton).toBeHidden();
        });

        await test.step('Enables the revocation button and check if it is visible', async () => {
            await TestDataService.setSystemConfig({ 'core.basicInformation.showRevocationButton': true });
            await ShopCustomer.goesTo(StorefrontHome.url());
            const revocationFormButton = StorefrontHome.page.getByText(/Revoke a contract|Vertrag widerrufen/);
            await expect(revocationFormButton).toBeVisible();
        });
    }
);