import { test, expect } from '@fixtures/AcceptanceTest';

const SELECTION = 'core.loginRegistration.showAccountTypeSelection';
const SHOW = 'core.loginRegistration.showNameFieldsForCompanyAccounts';
const REQUIRED = 'core.loginRegistration.nameFieldsRequiredForCompanyAccounts';

test.describe('Commercial accounts without a contact person', () => {
    test(
        'A commercial customer registers with a company name only when the shop hides the name fields.',
        {
            tag: [
                '@Registration',
                '@Storefront',
            ],
        },
        async ({
            ShopCustomer,
            StorefrontAccountLogin,
            StorefrontAccount,
            IdProvider,
            RegisterCompanyAccount,
            TestDataService,
        }) => {
            const uuid = IdProvider.getIdPair().uuid;
            const account = {
                email: `${uuid}@test.com`,
                company: `Acme ${uuid}`,
            };
            await TestDataService.setSystemConfig({
                [SELECTION]: true,
                [SHOW]: false,
                [REQUIRED]: false,
            });

            await test.step('The name fields disappear once the company account type is selected', async () => {
                await ShopCustomer.goesTo(StorefrontAccountLogin.url());
                await ShopCustomer.expects(StorefrontAccountLogin.firstNameInput).toBeVisible();
                await StorefrontAccountLogin.accountTypeSelect.selectOption('Commercial');
                await ShopCustomer.expects(StorefrontAccountLogin.firstNameInput).toBeHidden();
                await ShopCustomer.expects(StorefrontAccountLogin.lastNameInput).toBeHidden();
                await ShopCustomer.expects(StorefrontAccountLogin.companyInput).toBeVisible();
            });

            await test.step('Register with the company name only', async () => {
                await ShopCustomer.attemptsTo(RegisterCompanyAccount(account));
            });

            await test.step('The account overview names the company', async () => {
                await ShopCustomer.expects(StorefrontAccount.personalDataCardTitle).toBeVisible();
                await ShopCustomer.expects(StorefrontAccount.page.getByText(account.company).first()).toBeVisible();
                await ShopCustomer.expects(
                    StorefrontAccount.page.getByText(account.email, {
                        exact: true,
                    }),
                ).toBeVisible();

                const customer = await TestDataService.getCustomerByEmail(account.email);
                expect(customer).toEqual(
                    expect.objectContaining({
                        firstName: '',
                        lastName: '',
                        company: account.company,
                        displayName: account.company,
                    }),
                );
            });
        },
    );

    test(
        'The name fields stay required for a commercial customer while the settings keep their defaults.',
        {
            tag: [
                '@Registration',
                '@Storefront',
            ],
        },
        async ({ ShopCustomer, StorefrontAccountLogin, IdProvider, RegisterCompanyAccount, TestDataService }) => {
            const uuid = IdProvider.getIdPair().uuid;
            await TestDataService.setSystemConfig({
                [SELECTION]: true,
                [SHOW]: true,
                [REQUIRED]: true,
            });

            await test.step('Attempt to register without a contact person', async () => {
                await ShopCustomer.attemptsTo(
                    RegisterCompanyAccount({
                        email: `${uuid}@test.com`,
                        company: `Acme ${uuid}`,
                    }),
                );
            });

            await test.step('The registration is blocked on the name fields', async () => {
                await ShopCustomer.expects(StorefrontAccountLogin.page.getByText("I'm a new customer!")).toBeVisible();
                await ShopCustomer.expects(StorefrontAccountLogin.firstNameInput).toBeVisible();
                await ShopCustomer.expects(StorefrontAccountLogin.firstNameInput).toHaveCSS(
                    'border-color',
                    'rgb(194, 0, 23)',
                );
                await ShopCustomer.expects(StorefrontAccountLogin.lastNameInput).toHaveCSS(
                    'border-color',
                    'rgb(194, 0, 23)',
                );
                expect(await TestDataService.getCustomerByEmail(`${uuid}@test.com`)).toBeFalsy();
            });
        },
    );

    test(
        'A commercial customer may leave the visible name fields empty when the shop makes them optional.',
        {
            tag: [
                '@Registration',
                '@Storefront',
            ],
        },
        async ({
            ShopCustomer,
            StorefrontAccountLogin,
            StorefrontAccount,
            IdProvider,
            RegisterCompanyAccount,
            TestDataService,
        }) => {
            const uuid = IdProvider.getIdPair().uuid;
            const account = {
                email: `${uuid}@test.com`,
                company: `Acme ${uuid}`,
            };
            await TestDataService.setSystemConfig({
                [SELECTION]: true,
                [SHOW]: true,
                [REQUIRED]: false,
            });

            await test.step('The name fields stay visible for a company account', async () => {
                await ShopCustomer.goesTo(StorefrontAccountLogin.url());
                await StorefrontAccountLogin.accountTypeSelect.selectOption('Commercial');
                await ShopCustomer.expects(StorefrontAccountLogin.firstNameInput).toBeVisible();
                await ShopCustomer.expects(StorefrontAccountLogin.lastNameInput).toBeVisible();
            });

            await test.step('Register with the name fields left empty', async () => {
                await ShopCustomer.attemptsTo(RegisterCompanyAccount(account));
            });

            await test.step('The account overview names the company', async () => {
                await ShopCustomer.expects(StorefrontAccount.personalDataCardTitle).toBeVisible();
                await ShopCustomer.expects(StorefrontAccount.page.getByText(account.company).first()).toBeVisible();
            });
        },
    );

    test(
        'A commercial customer without a contact person is named by its company in the Storefront, the Store API, the order mail and the Administration.',
        {
            tag: [
                '@Registration',
                '@Storefront',
                '@Checkout',
            ],
        },
        async ({
            ShopCustomer,
            ShopAdmin,
            StorefrontAccount,
            StorefrontProductDetail,
            StorefrontCheckoutFinish,
            AdminCustomerListing,
            IdProvider,
            RegisterCompanyAccount,
            TestDataService,
            StoreApiContext,
            MailpitApiContext,
            AddProductToCart,
            ProceedFromProductToCheckout,
            ConfirmTermsAndConditions,
            SelectPaymentMethod,
            SelectShippingMethod,
            SubmitOrder,
        }) => {
            const uuid = IdProvider.getIdPair().uuid;
            const account = {
                email: `${uuid}@test.com`,
                company: `Acme ${uuid}`,
                password: 'shopware',
            };
            await TestDataService.setSystemConfig({
                [SELECTION]: true,
                [SHOW]: false,
                [REQUIRED]: false,
            });
            const product = await TestDataService.createBasicProduct();

            await test.step('Register a company account without a contact person', async () => {
                await ShopCustomer.attemptsTo(RegisterCompanyAccount(account));
                await ShopCustomer.expects(StorefrontAccount.page.getByText(account.company).first()).toBeVisible();
            });

            await test.step('The Store API resolves the display name to the company', async () => {
                await StoreApiContext.login({
                    email: account.email,
                    password: account.password,
                });
                const response = await StoreApiContext.get('account/customer');
                expect(response.ok()).toBeTruthy();
                const customer = await response.json();
                expect(customer.displayName).toBe(account.company);
                expect(customer.firstName).toBe('');
                expect(customer.lastName).toBe('');
            });

            await test.step('Place an order as the company account', async () => {
                await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
                await ShopCustomer.attemptsTo(AddProductToCart(product));
                await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
                await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
                await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
                await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));
                await ShopCustomer.attemptsTo(SubmitOrder());
                TestDataService.addCreatedRecord('order', StorefrontCheckoutFinish.getOrderId());
            });

            await test.step('The order confirmation mail addresses the company', async () => {
                await expect(async () => {
                    const headers = await MailpitApiContext.getEmailHeaders(account.email);
                    expect(headers.toName).toBe(account.company);
                }).toPass();

                const body = await MailpitApiContext.getEmailBody(account.email);
                expect(body).toContain(account.company);
                expect(body).not.toMatch(/Dear\s+Mr\.?\s*,/);
            });

            await test.step('The Administration lists the account by its company', async () => {
                await ShopAdmin.goesTo(AdminCustomerListing.url());
                const row = await AdminCustomerListing.getCustomerByEmail(account.email);
                await ShopAdmin.expects(row.customerName).toHaveText(account.company);
            });
        },
    );

    test(
        'The name settings of a sales channel decide the registration form and the checkout of its commercial customers.',
        {
            tag: [
                '@Registration',
                '@Storefront',
                '@Checkout',
            ],
        },
        async ({
            ShopCustomer,
            StorefrontAccount,
            StorefrontAccountLogin,
            StorefrontProductDetail,
            StorefrontCheckoutConfirm,
            StorefrontCheckoutFinish,
            IdProvider,
            RegisterCompanyAccount,
            TestDataService,
            AddProductToCart,
            ProceedFromProductToCheckout,
            Logout,
            ConfirmTermsAndConditions,
            SelectPaymentMethod,
            SelectShippingMethod,
            SubmitOrder,
        }) => {
            test.slow();

            const uuid = IdProvider.getIdPair().uuid;
            const account = {
                email: `${uuid}@test.com`,
                company: `Acme ${uuid}`,
            };
            const product = await TestDataService.createBasicProduct();

            const setGlobal = async (values: Record<string, boolean | null>) => {
                const response = await TestDataService.AdminApiClient.post('_action/system-config/batch', {
                    data: { null: values },
                });
                expect(response.ok()).toBeTruthy();
                await TestDataService.clearCaches();
            };

            const globalBefore = await (
                await TestDataService.AdminApiClient.get('_action/system-config?domain=core.loginRegistration')
            ).json();

            try {
                await test.step('The shop requires the contact person, the sales channel makes it optional', async () => {
                    await setGlobal({
                        [SELECTION]: true,
                        [SHOW]: true,
                        [REQUIRED]: true,
                    });
                    await TestDataService.setSystemConfig({
                        [SELECTION]: true,
                        [SHOW]: true,
                        [REQUIRED]: false,
                    });

                    await ShopCustomer.attemptsTo(RegisterCompanyAccount(account));
                    await ShopCustomer.expects(StorefrontAccount.personalDataCardTitle).toBeVisible();
                    await ShopCustomer.expects(StorefrontAccount.page.getByText(account.company).first()).toBeVisible();
                });

                await test.step('Requiring the names again in the sales channel blocks the checkout of the nameless account', async () => {
                    await TestDataService.setSystemConfig({ [REQUIRED]: true });

                    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
                    await ShopCustomer.attemptsTo(AddProductToCart(product));
                    await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());

                    await ShopCustomer.expects(
                        StorefrontCheckoutConfirm.page.locator('.alert-danger').first(),
                    ).toBeVisible();
                });

                await test.step('Making the names optional again lets the same account order', async () => {
                    await TestDataService.setSystemConfig({
                        [REQUIRED]: false,
                    });

                    await ShopCustomer.goesTo(StorefrontCheckoutConfirm.url());
                    await ShopCustomer.expects(StorefrontCheckoutConfirm.page.locator('.alert-danger')).toHaveCount(0);
                    await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
                    await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
                    await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));
                    await ShopCustomer.attemptsTo(SubmitOrder());
                    TestDataService.addCreatedRecord('order', StorefrontCheckoutFinish.getOrderId());
                });

                await test.step('The registration form of the sales channel follows its own required setting', async () => {
                    await TestDataService.setSystemConfig({ [REQUIRED]: true });
                    await ShopCustomer.attemptsTo(Logout());

                    await ShopCustomer.goesTo(StorefrontAccountLogin.url());
                    await StorefrontAccountLogin.accountTypeSelect.selectOption('Commercial');
                    await ShopCustomer.expects(StorefrontAccountLogin.firstNameInput).toHaveAttribute(
                        'aria-required',
                        'true',
                    );

                    await TestDataService.setSystemConfig({
                        [REQUIRED]: false,
                    });

                    await ShopCustomer.goesTo(StorefrontAccountLogin.url());
                    await StorefrontAccountLogin.accountTypeSelect.selectOption('Commercial');
                    await ShopCustomer.expects(StorefrontAccountLogin.firstNameInput).not.toHaveAttribute(
                        'aria-required',
                        'true',
                    );
                });
            } finally {
                await setGlobal({
                    [SELECTION]: globalBefore[SELECTION] ?? null,
                    [SHOW]: globalBefore[SHOW] ?? null,
                    [REQUIRED]: globalBefore[REQUIRED] ?? null,
                });
            }
        },
    );
});
