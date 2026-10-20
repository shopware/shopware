import { test, expect } from '@fixtures/AcceptanceTest';

const profileUrl = /\/account\/profile\?redirectTo=frontend\.checkout\.confirm\.page$/;

test(
    'Commercial guests add a missing VAT Reg.No. in their profile and complete the order.',
    {
        tag: [
            '@Checkout',
            '@Storefront',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        AdminApiContext,
        DefaultSalesChannel,
        StorefrontProductDetail,
        StorefrontCheckoutRegister,
        StorefrontAccountLogin,
        StorefrontCheckoutConfirm,
        StorefrontAccountProfile,
        AddProductToCart,
        Register,
        ConfirmTermsAndConditions,
        SubmitOrder,
    }) => {
        await TestDataService.setSystemConfig({
            'core.loginRegistration.showAccountTypeSelection': true,
            'core.loginRegistration.allowCustomerDeletion': true,
        });
        const country = await TestDataService.createCountry({ vatIdRequired: false });
        await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.salesChannel.id, country.id);
        const product = await TestDataService.createBasicProduct();

        const vatIdErrorLink = StorefrontCheckoutConfirm.page.locator(
            '.alert a[href*="account/profile?redirectTo=frontend.checkout.confirm.page"]',
        );
        const vatIdInput = StorefrontAccountProfile.page.locator('#vatIds');

        await test.step('Register as a commercial guest without a VAT Reg.No. in the checkout', async () => {
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.attemptsTo(AddProductToCart(product));
            await ShopCustomer.presses(StorefrontProductDetail.offCanvasCartGoToCheckoutButton);
            await StorefrontCheckoutRegister.page.waitForURL('**/checkout/register', { waitUntil: 'commit' });

            await ShopCustomer.presses(StorefrontAccountLogin.accountTypeSelect);
            await StorefrontAccountLogin.accountTypeSelect.selectOption('Commercial');
            await ShopCustomer.attemptsTo(
                Register({ isCommercial: true, isGuest: true, country: country.name, vatRegNo: '' }),
            );
            await ShopCustomer.expects(StorefrontCheckoutConfirm.headline).toBeVisible();
        });

        await test.step('The country of the billing address starts requiring a VAT Reg.No.', async () => {
            const response = await AdminApiContext.patch(`country/${country.id}`, {
                data: { vatIdRequired: true },
            });
            expect(response.ok()).toBeTruthy();
        });

        await test.step('The checkout blocks the order and links to the profile', async () => {
            await ShopCustomer.goesTo(StorefrontCheckoutConfirm.url());
            await ShopCustomer.expects(vatIdErrorLink).toBeVisible();
            await ShopCustomer.expects(StorefrontCheckoutConfirm.submitOrderButton).toBeDisabled();

            await ShopCustomer.presses(vatIdErrorLink);
            await StorefrontAccountProfile.page.waitForURL(profileUrl);
        });

        await test.step('The profile of the guest only offers the personal data', async () => {
            await ShopCustomer.expects(vatIdInput).toBeVisible();
            await ShopCustomer.expects(vatIdInput).toHaveAttribute('aria-required');
            await ShopCustomer.expects(StorefrontAccountProfile.changeEmailButton).toHaveCount(0);
            await ShopCustomer.expects(StorefrontAccountProfile.changePasswordButton).toHaveCount(0);
            await ShopCustomer.expects(StorefrontAccountProfile.page.locator('.account-deleting')).toHaveCount(0);
        });

        await test.step('Saving the VAT Reg.No. returns to the checkout, where the order can be placed', async () => {
            await ShopCustomer.fillsIn(vatIdInput, 'DE123456789');
            await ShopCustomer.presses(StorefrontAccountProfile.saveProfileButton);
            await StorefrontCheckoutConfirm.page.waitForURL('**/checkout/confirm');

            await ShopCustomer.expects(vatIdErrorLink).toHaveCount(0);
            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
            await ShopCustomer.attemptsTo(SubmitOrder());
        });
    },
);
