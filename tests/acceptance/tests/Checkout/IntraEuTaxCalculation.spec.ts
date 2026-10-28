import { test, expect, formatPrice } from '@fixtures/AcceptanceTest';

interface Country {
    id: string;
    iso: string;
    active: boolean;
    shippingAvailable: boolean;
    isEu: boolean;
    checkVatIdPattern: boolean;
    companyTax: TaxFreeConfig;
    customerTax: TaxFreeConfig;
}

interface TaxFreeConfig {
    enabled: boolean;
    currencyId: string;
    amount: number;
}

function getTaxConfig({ enabled, currencyId, amount }: TaxFreeConfig): TaxFreeConfig {
    return { enabled, currencyId, amount };
}

let countries: Country[] = [];
let taxId: string;
let taxRuleId: string;

test.describe.configure({ mode: 'default' });

test.beforeAll(async ({ AdminApiContext, IdProvider }) => {
    const countryResponse = await AdminApiContext.post('search/country', {
        data: {
            filter: [{ type: 'equalsAny', field: 'iso', value: ['DE', 'FR'] }],
        },
    });
    expect(countryResponse.ok()).toBeTruthy();
    const { data } = (await countryResponse.json()) as { data: Country[] };
    countries = data.map((country) => ({
        ...country,
        companyTax: getTaxConfig(country.companyTax),
        customerTax: getTaxConfig(country.customerTax),
    }));
    expect(countries).toHaveLength(2);
    const france = countries.find((country) => country.iso === 'FR')!;

    const ruleTypeResponse = await AdminApiContext.post('search/tax-rule-type', {
        data: {
            filter: [{ type: 'equals', field: 'technicalName', value: 'entire_country' }],
        },
    });
    expect(ruleTypeResponse.ok()).toBeTruthy();
    const { data: ruleTypes } = await ruleTypeResponse.json();

    taxId = IdProvider.getIdPair().uuid;
    taxRuleId = IdProvider.getIdPair().uuid;
    const taxResponse = await AdminApiContext.post('tax', {
        data: {
            id: taxId,
            name: `Intra-EU checkout ${taxId}`,
            taxRate: 19,
            rules: [
                {
                    id: taxRuleId,
                    countryId: france.id,
                    taxRuleTypeId: ruleTypes[0].id,
                    taxRate: 20,
                },
            ],
        },
    });
    expect(taxResponse.ok()).toBeTruthy();
});

test.afterAll(async ({ AdminApiContext }) => {
    for (const country of countries) {
        const countryResponse = await AdminApiContext.patch(`country/${country.id}`, {
            data: {
                active: country.active,
                shippingAvailable: country.shippingAvailable,
                isEu: country.isEu,
                checkVatIdPattern: country.checkVatIdPattern,
                companyTax: country.companyTax,
                customerTax: country.customerTax,
            },
        });
        expect(countryResponse.ok()).toBeTruthy();
    }

    if (taxId) {
        const ruleResponse = await AdminApiContext.delete(`tax-rule/${taxRuleId}`);
        expect(ruleResponse.ok()).toBeTruthy();
        const taxResponse = await AdminApiContext.delete(`tax/${taxId}`);
        expect(taxResponse.ok()).toBeTruthy();
    }
});

// One case per outcome, the full VAT ID and delivery country matrix is covered by IntraCommunityTaxRuleTest
const scenarios = [
    { name: 'another member state VAT ID', vatIds: ['NL123456789B01'], taxRate: 0, taxAmount: 0 },
    { name: 'a seller-country VAT ID', vatIds: ['DE123456789'], taxRate: 19, taxAmount: 1.6 },
    { name: 'no VAT ID', vatIds: [], taxRate: 20, taxAmount: 1.67 },
];

for (const scenario of scenarios) {
    test(
        `A German seller charges ${scenario.taxRate}% VAT for goods delivered to France with ${scenario.name}.`,
        {
            tag: [
                '@Checkout',
                '@Storefront',
            ],
        },
        async (
            {
                ShopCustomer,
                TestDataService,
                DefaultSalesChannel,
                AdminApiContext,
                StorefrontProductDetail,
                StorefrontCheckoutConfirm,
                StorefrontCheckoutFinish,
                Login,
                AddProductToCart,
                ProceedFromProductToCheckout,
                ConfirmTermsAndConditions,
                SelectPaymentMethod,
                SelectShippingMethod,
                SubmitOrder,
            },
            testInfo,
        ) => {
            const germany = countries.find((country) => country.iso === 'DE')!;
            const france = countries.find((country) => country.iso === 'FR')!;

            await test.step('Configure seller and destination VAT for the goods delivery', async () => {
                await TestDataService.setSystemConfig({ 'core.basicInformation.sellerCountryId': germany.id });
                await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.salesChannel.id, france.id);

                const countryResponse = await AdminApiContext.patch(`country/${france.id}`, {
                    data: {
                        active: true,
                        shippingAvailable: true,
                        isEu: true,
                        checkVatIdPattern: true,
                        customerTax: { ...france.customerTax, enabled: false },
                        companyTax: { ...france.companyTax, enabled: true },
                    },
                });
                expect(countryResponse.ok()).toBeTruthy();
                await TestDataService.clearCaches();
            });

            const salutation = await TestDataService.getSalutation();
            const address = {
                firstName: 'John',
                lastName: 'Doe',
                company: 'EU Business',
                street: 'Business street 1',
                city: 'Berlin',
                zipcode: '10115',
                countryId: germany.id,
                salutationId: salutation.id,
            };
            const customer = await TestDataService.createCustomer({
                accountType: 'business',
                company: address.company,
                vatIds: scenario.vatIds,
                defaultBillingAddress: address,
                defaultShippingAddress: { ...address, city: 'Paris', zipcode: '75001', countryId: france.id },
            });
            const product = await TestDataService.createBasicProduct({}, taxId);

            await test.step('Check the VAT rate in the browser checkout', async () => {
                await ShopCustomer.attemptsTo(Login(customer));
                await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
                await ShopCustomer.attemptsTo(AddProductToCart(product));
                await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
                await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
                await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));
                await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());

                const screenshot = testInfo.outputPath('checkout-confirm.png');
                await StorefrontCheckoutConfirm.page.screenshot({ path: screenshot, fullPage: true });
                await testInfo.attach('Checkout VAT calculation', { path: screenshot, contentType: 'image/png' });

                const taxLabels = StorefrontCheckoutConfirm.page.locator('dt.summary-tax');
                // eslint-disable-next-line playwright/no-conditional-in-test
                if (scenario.taxRate === 0) {
                    await ShopCustomer.expects(taxLabels).toHaveCount(0);
                } else {
                    await ShopCustomer.expects(taxLabels).toHaveCount(1);
                    await ShopCustomer.expects(taxLabels).toContainText(`${scenario.taxRate}%`);
                }
            });

            await test.step('Place the order and verify the persisted product VAT rate', async () => {
                await ShopCustomer.attemptsTo(SubmitOrder());
                const orderId = StorefrontCheckoutFinish.getOrderId();
                TestDataService.addCreatedRecord('order', orderId);

                const screenshot = testInfo.outputPath('checkout-finish.png');
                await StorefrontCheckoutFinish.page.screenshot({ path: screenshot, fullPage: true });
                await testInfo.attach('Completed order VAT calculation', { path: screenshot, contentType: 'image/png' });

                const taxLabels = StorefrontCheckoutFinish.page.locator('dt.summary-tax');
                // eslint-disable-next-line playwright/no-conditional-in-test
                if (scenario.taxRate === 0) {
                    await ShopCustomer.expects(taxLabels).toHaveCount(0);
                } else {
                    await ShopCustomer.expects(taxLabels).toContainText(`${scenario.taxRate}%`);
                }

                const lineItemResponse = await AdminApiContext.post('search/order-line-item', {
                    data: {
                        filter: [
                            { type: 'equals', field: 'orderId', value: orderId },
                            { type: 'equals', field: 'productId', value: product.id },
                        ],
                    },
                });
                expect(lineItemResponse.ok()).toBeTruthy();
                const { data: lineItems } = await lineItemResponse.json();
                expect(lineItems).toHaveLength(1);
                const expectedProductTax = expect.objectContaining({
                    taxRate: scenario.taxRate,
                    tax: scenario.taxAmount,
                });
                expect(lineItems[0].price.calculatedTaxes).toEqual(scenario.taxRate === 0 ? [] : [expectedProductTax]);

                const orderResponse = await AdminApiContext.post('search/order', {
                    data: { ids: [orderId], associations: { deliveries: { associations: { shippingOrderAddress: {} } } } },
                });
                expect(orderResponse.ok()).toBeTruthy();
                const { data: orders } = await orderResponse.json();
                expect(orders[0].price.taxStatus).toBe(scenario.taxRate === 0 ? 'tax-free' : 'gross');
                expect(orders[0].deliveries[0].shippingOrderAddress.countryId).toBe(france.id);
            });
        },
    );
}

test(
    'A net-price customer cannot lower destination VAT by adding a seller-country VAT ID when business tax exemption is disabled.',
    {
        tag: [
            '@Checkout',
            '@Storefront',
        ],
    },
    async (
        {
            ShopCustomer,
            TestDataService,
            DefaultSalesChannel,
            AdminApiContext,
            StoreApiContext,
            SalesChannelBaseConfig,
            StorefrontProductDetail,
            StorefrontCheckoutConfirm,
            StorefrontCheckoutFinish,
            Login,
            AddProductToCart,
            ProceedFromProductToCheckout,
            ConfirmTermsAndConditions,
            SelectPaymentMethod,
            SelectShippingMethod,
            SubmitOrder,
        },
        testInfo,
    ) => {
        const germany = countries.find((country) => country.iso === 'DE')!;
        const france = countries.find((country) => country.iso === 'FR')!;

        await test.step('Disable French business tax exemption and enable VAT format validation', async () => {
            await TestDataService.setSystemConfig({ 'core.basicInformation.sellerCountryId': germany.id });
            await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.salesChannel.id, france.id);

            const franceResponse = await AdminApiContext.patch(`country/${france.id}`, {
                data: {
                    active: true,
                    shippingAvailable: true,
                    isEu: true,
                    checkVatIdPattern: true,
                    customerTax: { ...france.customerTax, enabled: false },
                    companyTax: { ...france.companyTax, enabled: false },
                },
            });
            expect(franceResponse.ok()).toBeTruthy();
            const germanyResponse = await AdminApiContext.patch(`country/${germany.id}`, {
                data: { checkVatIdPattern: true },
            });
            expect(germanyResponse.ok()).toBeTruthy();
            await TestDataService.clearCaches();
        });

        const customerGroup = await TestDataService.createCustomerGroup({ displayGross: false, registrationActive: false });
        const salutation = await TestDataService.getSalutation();
        const address = {
            firstName: 'John',
            lastName: 'Doe',
            company: 'EU Business',
            street: 'Business street 1',
            city: 'Berlin',
            zipcode: '10115',
            countryId: germany.id,
            salutationId: salutation.id,
        };
        const customer = await TestDataService.createCustomer({
            accountType: 'business',
            company: address.company,
            groupId: customerGroup.id,
            vatIds: [],
            defaultBillingAddress: address,
            defaultShippingAddress: { ...address, city: 'Paris', zipcode: '75001', countryId: france.id },
        });
        const product = await TestDataService.createBasicProduct(
            {
                price: [
                    ...new Set([DefaultSalesChannel.salesChannel.currencyId, SalesChannelBaseConfig.defaultCurrencyId]),
                ].map((currencyId) => ({ currencyId, net: 10, gross: 11.9, linked: true })),
            },
            taxId,
        );

        await test.step('Add a format-valid seller-country VAT ID through the public customer profile API', async () => {
            await StoreApiContext.login(customer);
            const profileResponse = await StoreApiContext.post('account/change-profile', {
                data: {
                    accountType: 'business',
                    company: address.company,
                    firstName: customer.firstName,
                    lastName: customer.lastName,
                    salutationId: salutation.id,
                    vatIds: ['DE123456789'],
                },
            });
            expect(profileResponse.ok()).toBeTruthy();
            expect((await profileResponse.json()).success).toBe(true);

            const customerResponse = await StoreApiContext.get('account/customer');
            expect(customerResponse.ok()).toBeTruthy();
            expect((await customerResponse.json()).vatIds).toEqual(['DE123456789']);
        });

        await test.step('Keep destination VAT in the net-price browser checkout', async () => {
            await ShopCustomer.attemptsTo(Login(customer));
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.attemptsTo(AddProductToCart(product));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
            await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));
            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());

            const screenshot = testInfo.outputPath('checkout-confirm.png');
            await StorefrontCheckoutConfirm.page.screenshot({ path: screenshot, fullPage: true });
            await testInfo.attach('Public VAT edit checkout calculation', { path: screenshot, contentType: 'image/png' });

            const taxLabels = StorefrontCheckoutConfirm.page.locator('dt.summary-tax');
            await ShopCustomer.expects(taxLabels).toHaveCount(1);
            await ShopCustomer.expects(taxLabels).toContainText('20%');
            await ShopCustomer.expects(StorefrontCheckoutConfirm.taxPrice).toContainText(formatPrice(2));
            await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toContainText(formatPrice(12));
        });

        await test.step('Persist destination tax and the French delivery address on the net-price order', async () => {
            await ShopCustomer.attemptsTo(SubmitOrder());
            const orderId = StorefrontCheckoutFinish.getOrderId();
            TestDataService.addCreatedRecord('order', orderId);

            const screenshot = testInfo.outputPath('checkout-finish.png');
            await StorefrontCheckoutFinish.page.screenshot({ path: screenshot, fullPage: true });
            await testInfo.attach('Public VAT edit completed order', { path: screenshot, contentType: 'image/png' });

            await ShopCustomer.expects(StorefrontCheckoutFinish.page.locator('dt.summary-tax')).toContainText('20%');
            await ShopCustomer.expects(StorefrontCheckoutFinish.taxPrice).toContainText(formatPrice(2));
            await ShopCustomer.expects(StorefrontCheckoutFinish.grandTotalPrice).toContainText(formatPrice(12));

            const lineItemResponse = await AdminApiContext.post('search/order-line-item', {
                data: {
                    filter: [
                        { type: 'equals', field: 'orderId', value: orderId },
                        { type: 'equals', field: 'productId', value: product.id },
                    ],
                },
            });
            expect(lineItemResponse.ok()).toBeTruthy();
            const { data: lineItems } = await lineItemResponse.json();
            expect(lineItems).toHaveLength(1);
            expect(lineItems[0].price).toEqual(
                expect.objectContaining({
                    totalPrice: 10,
                    calculatedTaxes: [expect.objectContaining({ taxRate: 20, tax: 2 })],
                }),
            );

            const orderResponse = await AdminApiContext.post('search/order', {
                data: { ids: [orderId], associations: { deliveries: { associations: { shippingOrderAddress: {} } } } },
            });
            expect(orderResponse.ok()).toBeTruthy();
            const { data: orders } = await orderResponse.json();
            expect(orders[0].price).toEqual(expect.objectContaining({ taxStatus: 'net', netPrice: 10, totalPrice: 12 }));
            expect(orders[0].deliveries[0].shippingOrderAddress.countryId).toBe(france.id);
        });
    },
);
