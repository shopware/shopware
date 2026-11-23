import { test, expect } from '@fixtures/AcceptanceTest';

test(
    'Commercial customers keep company and VAT Reg.No. when saving the profile without the account type selection.',
    {
        tag: [
            '@Account',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, TestDataService, AdminApiContext, StorefrontAccountProfile, Login }) => {
        await TestDataService.setSystemConfig({ 'core.loginRegistration.showAccountTypeSelection': false });
        const customer = await TestDataService.createCustomer({
            accountType: 'business',
            company: 'Example Inc.',
            vatIds: ['DE123456789'],
        });

        const profileForm = StorefrontAccountProfile.page.locator('#profilePersonalForm');
        const companyInput = profileForm.locator('input[name="company"]');
        const vatIdInput = profileForm.locator('#vatIds');

        await test.step('The profile shows company and VAT Reg.No. of the commercial customer', async () => {
            await ShopCustomer.attemptsTo(Login(customer));
            await ShopCustomer.goesTo(StorefrontAccountProfile.url());

            await ShopCustomer.expects(companyInput).toHaveValue('Example Inc.');
            await ShopCustomer.expects(vatIdInput).toHaveValue('DE123456789');
        });

        await test.step('Changing only the first name keeps account type, company and VAT Reg.No.', async () => {
            await ShopCustomer.fillsIn(StorefrontAccountProfile.firstNameInput, 'Jane');
            await ShopCustomer.presses(StorefrontAccountProfile.saveProfileButton);
            await ShopCustomer.expects(StorefrontAccountProfile.firstNameInput).toHaveValue('Jane');

            const response = await AdminApiContext.get(`customer/${customer.id}`);
            expect(response.ok()).toBeTruthy();

            const { data } = await response.json();
            expect(data).toEqual(
                expect.objectContaining({
                    firstName: 'Jane',
                    accountType: 'business',
                    company: 'Example Inc.',
                    vatIds: ['DE123456789'],
                }),
            );
        });
    },
);
