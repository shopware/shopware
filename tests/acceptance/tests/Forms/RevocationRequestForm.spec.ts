import { expect, test } from '@fixtures/AcceptanceTest';

test(
    'As a merchant, I want to switch on and off the revocation request form',
    { tag: ['@Form', '@Revocation', '@Storefront'] },
    async ({ ShopCustomer, StorefrontHome, TestDataService }) => {
        const revocationFormButton = () => {
            return StorefrontHome.page.getByRole('link', { name: /Revoke a contract|Vertrag widerrufen/i });
        };

        const openStorefrontHome = async () => {
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
        };

        await test.step('Visit the home page to check there is no revocation button', async () => {
            await TestDataService.setSystemConfig({ 'core.basicInformation.showRevocationButton': false });

            await ShopCustomer.expects(async () => {
                await openStorefrontHome();
                await expect(revocationFormButton()).toBeHidden();
            }).toPass({
                intervals: [1_000, 2_500],
            });
        });

        await test.step('Enables the revocation button and check if it is visible', async () => {
            await TestDataService.setSystemConfig({ 'core.basicInformation.showRevocationButton': true });

            await ShopCustomer.expects(async () => {
                await openStorefrontHome();
                await expect(revocationFormButton()).toBeVisible();
            }).toPass({
                intervals: [1_000, 2_500],
            });
        });
    }
);
