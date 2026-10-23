import { test, expect } from '@fixtures/AcceptanceTest';

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
            filter: [{ type: 'equalsAny', field: 'iso', value: ['DE', 'FR', 'CH'] }],
        },
    });
    expect(countryResponse.ok()).toBeTruthy();
    const { data } = (await countryResponse.json()) as { data: Country[] };
    countries = data.map((country) => ({
        ...country,
        companyTax: getTaxConfig(country.companyTax),
        customerTax: getTaxConfig(country.customerTax),
    }));
    expect(countries).toHaveLength(3);
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

interface VatStatus {
    name: string;
    vatIds: string[];
    accountType: 'business' | 'private';
    taxCategory: 'foreign' | 'seller' | 'none';
}

const vatStatuses: VatStatus[] = [
    {
        name: 'another member state VAT ID',
        vatIds: ['NL123456789B01'],
        accountType: 'business',
        taxCategory: 'foreign',
    },
    {
        name: 'seller-country VAT ID',
        vatIds: ['DE123456789'],
        accountType: 'business',
        taxCategory: 'seller',
    },
    { name: 'no VAT ID', vatIds: [], accountType: 'business', taxCategory: 'none' },
    { name: 'an invalid VAT ID', vatIds: ['INVALID'], accountType: 'business', taxCategory: 'none' },
    { name: 'a private customer', vatIds: [], accountType: 'private', taxCategory: 'none' },
];

const destinations = [
    {
        iso: 'CH',
        name: 'Switzerland',
        city: 'Zurich',
        zipcode: '8001',
        isEu: false,
        taxRates: { foreign: 0, seller: 0, none: 0 },
    },
    {
        iso: 'FR',
        name: 'France',
        city: 'Paris',
        zipcode: '75001',
        isEu: true,
        taxRates: { foreign: 0, seller: 19, none: 20 },
    },
    {
        iso: 'DE',
        name: 'Germany',
        city: 'Berlin',
        zipcode: '10115',
        isEu: true,
        taxRates: { foreign: 19, seller: 19, none: 19 },
    },
];

const matrix = destinations.flatMap((destination) =>
    vatStatuses.map((vatStatus) => {
        const taxRate = destination.taxRates[vatStatus.taxCategory];

        return {
            ...vatStatus,
            company: vatStatus.accountType === 'business' ? 'EU Business' : '',
            destination,
            checkVatIdPattern: true,
            taxRate,
            taxAmount: taxRate === 19 ? 1.6 : taxRate === 20 ? 1.67 : 0,
        };
    }),
);

const scenarios = [
    ...matrix,
    ...matrix
        .filter((scenario) => scenario.destination.iso === 'FR' && scenario.taxCategory !== 'none')
        .map((scenario) => ({
            ...scenario,
            name: `${scenario.name} with format validation disabled`,
            checkVatIdPattern: false,
        })),
];

for (const scenario of scenarios) {
    test(
        `A German seller charges ${scenario.taxRate}% VAT for goods delivered to ${scenario.destination.name} with ${scenario.name}.`,
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
            const destinationCountry = countries.find((country) => country.iso === scenario.destination.iso)!;

            await test.step('Configure seller and destination VAT for the goods delivery', async () => {
                await TestDataService.setSystemConfig({ 'core.basicInformation.sellerCountryId': germany.id });
                await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.salesChannel.id, destinationCountry.id);

                const countryResponse = await AdminApiContext.patch(`country/${destinationCountry.id}`, {
                    data: {
                        active: true,
                        shippingAvailable: true,
                        isEu: scenario.destination.isEu,
                        checkVatIdPattern: scenario.checkVatIdPattern,
                        customerTax: { ...destinationCountry.customerTax, enabled: !scenario.destination.isEu },
                        companyTax: { ...destinationCountry.companyTax, enabled: true },
                    },
                });
                expect(countryResponse.ok()).toBeTruthy();
                await TestDataService.clearCaches();
            });

            const salutation = await TestDataService.getSalutation();
            const address = {
                firstName: 'John',
                lastName: 'Doe',
                company: scenario.company,
                street: 'Business street 1',
                city: 'Berlin',
                zipcode: '10115',
                countryId: germany.id,
                salutationId: salutation.id,
            };
            const customer = await TestDataService.createCustomer({
                accountType: scenario.accountType,
                company: address.company,
                vatIds: scenario.vatIds,
                defaultBillingAddress: address,
                defaultShippingAddress: {
                    ...address,
                    city: scenario.destination.city,
                    zipcode: scenario.destination.zipcode,
                    countryId: destinationCountry.id,
                },
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
                expect(orders[0].deliveries[0].shippingOrderAddress.countryId).toBe(destinationCountry.id);
            });
        },
    );
}
