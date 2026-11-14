import { test, formatPrice } from '@fixtures/AcceptanceTest';
import type { Address, CountryRecord } from '@shopware-ag/acceptance-test-suite';

/**
 * Acceptance criteria from "Intra-Community VAT Exemption for Cross-Border B2B
 * Deliveries" and the goods matrix in "VAT Calculation in the EU".
 *
 * The reported case is a commercial customer with Dutch VAT ID NL123456789B01.
 * Shop owner's country is Germany, so a delivery to the Netherlands is not a
 * domestic supply. Company tax-free and VAT ID format checks are off in the demo
 * data; this file turns them on for the countries under test and restores them.
 * A taxed cart shows plus 19% VAT and a tax of £3.19 on a £20.00 total.
 * A tax-free cart has no percentage row. It shows excl. VAT £0.00 and a payable total of £16.80.
 * One tax-free order and one taxed order are placed, then checked again on the finish page and in the account.
 * The goods matrix is three VAT ID statuses times three deliveries: outside the EU,
 * to another EU country, and inside the shop country.
 */

const SELLER_COUNTRY_CONFIG = 'core.basicInformation.sellerCountryId';
const CART_TAX_COLUMN_CONFIG = 'core.cart.columnTaxInsteadUnitPrice';
const DUTCH_VAT_ID = 'NL123456789B01';
const GERMAN_VAT_ID = 'DE123456789';
const SWISS_VAT_ID = 'CHE123456789';
const PRODUCT_TAX_RATE = 19;
const STANDARD_PRODUCT_TAX = formatPrice(3.19);
const TAXED_CART_TOTAL = formatPrice(20);
const TAX_FREE_PRICE = formatPrice(0);
const TAX_FREE_CART_TOTAL = formatPrice(16.8);

const deliveryCountries = ['NL', 'BE', 'DE', 'CH'] as const;

const originalCountries = new Map<string, CountryRecord>();

let homeCountryId = '';

function address(iso: (typeof deliveryCountries)[number]): Pick<Address, 'city' | 'street' | 'zipcode'> & { countryId: string } {
    const addresses: Record<(typeof deliveryCountries)[number], { city: string; zipcode: string }> = {
        NL: { city: 'Amsterdam', zipcode: '1012AB' },
        BE: { city: 'Brussels', zipcode: '1000' },
        DE: { city: 'Schöppingen', zipcode: '48624' },
        CH: { city: 'Zurich', zipcode: '8001' },
    };

    return {
        countryId: originalCountries.get(iso)!.id,
        street: 'Main 1',
        ...addresses[iso],
    };
}

test.describe('Intra-community VAT exemption for cross-border B2B deliveries', () => {
    test.describe.configure({ mode: 'serial' });

    test.beforeAll(async ({ TestDataService }) => {
        // Settings > Sales channels: keep the country already assigned to this channel.
        homeCountryId = TestDataService.defaultSalesChannel.countryId;

        for (const iso of deliveryCountries) {
            // Settings > Countries: open Netherlands, Belgium, Germany, or Switzerland.
            const record = await TestDataService.getCountry(iso);

            // Remember the current tax-free and VAT-format settings so they can be restored.
            originalCountries.set(iso, {
                id: record.id,
                companyTax: {
                    enabled: false,
                    currencyId: record.companyTax.currencyId,
                    amount: record.companyTax.amount ?? 0,
                },
                checkVatIdPattern: false,
            });

            // Enable "Tax-free for companies". Also enable "Check VAT Reg. No. format", except for Switzerland.
            await TestDataService.updateCountry(record.id, {
                companyTax: { ...record.companyTax, enabled: true },
                checkVatIdPattern: iso !== 'CH',
            });
        }

        // Settings > Sales channels > Countries: allow shipping to those countries.
        await TestDataService.assignSalesChannelCountries([
            homeCountryId,
            ...[...originalCountries.values()].map((country) => country.id),
        ]);
        // Settings > Caches: clear the cache so checkout uses the new country settings.
        await TestDataService.clearCaches();
    });

    test.beforeEach(async ({ TestDataService }) => {
        // Settings > Countries: open Germany, the shop owner's country for these cases.
        const germany = await TestDataService.getCountry('DE');

        // Settings > Shop > Basic information: set "Shop owner's country" to Germany.
        // Settings > Cart: show the tax column instead of the unit price. Tax-free checkout labels it "excl. VAT".
        await TestDataService.setSystemConfig({
            [SELLER_COUNTRY_CONFIG]: germany.id,
            [CART_TAX_COLUMN_CONFIG]: true,
        });
    });

    test.afterAll(async ({ TestDataService }) => {
        for (const country of originalCountries.values()) {
            // Settings > Countries: turn "Tax-free for companies" and "Check VAT Reg. No. format" back off.
            await TestDataService.updateCountry(country.id, {
                companyTax: country.companyTax,
                checkVatIdPattern: country.checkVatIdPattern,
            });
        }

        // Settings > Sales channels > Countries: remove the added countries and keep the original one.
        await TestDataService.removeSalesChannelCountries(
            [...originalCountries.values()].map((country) => country.id).filter((countryId) => countryId !== homeCountryId),
        );
        // Settings > Caches: clear the cache so checkout returns to the previous country settings.
        await TestDataService.clearCaches();
    });

    test(
        'A commercial customer with a Dutch VAT ID is tax-free when the goods are delivered to Belgium.',
        { tag: ['@Checkout', '@Storefront'] },
        async ({
            IdProvider,
            ShopCustomer,
            TestDataService,
            StorefrontProductDetail,
            StorefrontCheckoutConfirm,
            StorefrontCheckoutFinish,
            StorefrontAccountOrder,
            Login,
            AddProductToCart,
            ProceedFromProductToCheckout,
            ConfirmTermsAndConditions,
            SelectPaymentMethod,
            SelectShippingMethod,
            SubmitOrder,
        }) => {
            const customer = await TestDataService.createCustomer({
                accountType: 'business',
                company: `Intra EU Buyer ${IdProvider.getIdPair().id}`,
                vatIds: [DUTCH_VAT_ID],
                defaultBillingAddress: address('NL'),
                defaultShippingAddress: address('BE'),
            });
            const tax = await TestDataService.createTaxRate({ taxRate: 19 });
            const product = await TestDataService.createBasicProduct({}, tax.id);

            await ShopCustomer.attemptsTo(Login(customer));
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.attemptsTo(AddProductToCart(product, '2'));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            const lineItem = StorefrontCheckoutConfirm.getLineItemByProductName(product.name);
            await ShopCustomer.expects(lineItem.taxPrice).toHaveText(StorefrontCheckoutConfirm.excludedVatText(TAX_FREE_PRICE));
            await ShopCustomer.expects(lineItem.productTotalPrice).toHaveText(TAX_FREE_CART_TOTAL);
            await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toHaveText(TAX_FREE_CART_TOTAL);

            await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
            await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));
            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
            await ShopCustomer.attemptsTo(SubmitOrder());
            const orderId = StorefrontCheckoutFinish.getOrderId();
            TestDataService.addCreatedRecord('order', orderId);
            const orderNumber = await StorefrontCheckoutFinish.getOrderNumber();
            await ShopCustomer.expects(StorefrontCheckoutFinish.taxPrice).toHaveCount(0);
            await ShopCustomer.expects(StorefrontCheckoutFinish.grandTotalPrice).toHaveText(TAX_FREE_CART_TOTAL);

            await ShopCustomer.goesTo(StorefrontAccountOrder.url());
            const orderLocators = await StorefrontAccountOrder.getOrderByOrderNumber(orderNumber ?? '');
            await ShopCustomer.presses(orderLocators.orderDetailButton);
            await ShopCustomer.expects(orderLocators.taxPrice).toHaveCount(0);
            await ShopCustomer.expects(orderLocators.totalNet).toHaveText(TAX_FREE_CART_TOTAL);
        },
    );

    test(
        'A commercial customer with a Dutch VAT ID stays tax-free when the goods are delivered to the Netherlands.',
        { tag: ['@Checkout', '@Storefront'] },
        async ({
            IdProvider,
            ShopCustomer,
            TestDataService,
            StorefrontProductDetail,
            StorefrontCheckoutConfirm,
            Login,
            AddProductToCart,
            ProceedFromProductToCheckout,
        }) => {
            const customer = await TestDataService.createCustomer({
                accountType: 'business',
                company: `Intra EU Buyer ${IdProvider.getIdPair().id}`,
                vatIds: [DUTCH_VAT_ID],
                defaultBillingAddress: address('NL'),
                defaultShippingAddress: address('NL'),
            });
            const tax = await TestDataService.createTaxRate({ taxRate: 19 });
            const product = await TestDataService.createBasicProduct({}, tax.id);

            await ShopCustomer.attemptsTo(Login(customer));
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.attemptsTo(AddProductToCart(product, '2'));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            const lineItem = StorefrontCheckoutConfirm.getLineItemByProductName(product.name);
            await ShopCustomer.expects(lineItem.taxPrice).toHaveText(StorefrontCheckoutConfirm.excludedVatText(TAX_FREE_PRICE));
            await ShopCustomer.expects(lineItem.productTotalPrice).toHaveText(TAX_FREE_CART_TOTAL);
            await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toHaveText(TAX_FREE_CART_TOTAL);
        },
    );

    test(
        'A Swiss VAT ID does not exempt a delivery to Belgium.',
        { tag: ['@Checkout', '@Storefront'] },
        async ({
            IdProvider,
            ShopCustomer,
            TestDataService,
            StorefrontProductDetail,
            StorefrontCheckoutConfirm,
            Login,
            AddProductToCart,
            ProceedFromProductToCheckout,
        }) => {
            const customer = await TestDataService.createCustomer({
                accountType: 'business',
                company: `Intra EU Buyer ${IdProvider.getIdPair().id}`,
                vatIds: [SWISS_VAT_ID],
                defaultBillingAddress: address('NL'),
                defaultShippingAddress: address('BE'),
            });
            const tax = await TestDataService.createTaxRate({ taxRate: 19 });
            const product = await TestDataService.createBasicProduct({}, tax.id);

            await ShopCustomer.attemptsTo(Login(customer));
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.attemptsTo(AddProductToCart(product, '2'));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            const vat = StorefrontCheckoutConfirm.getTaxSummary(PRODUCT_TAX_RATE);
            await ShopCustomer.expects(vat.label).toHaveText(StorefrontCheckoutConfirm.vatLabel(PRODUCT_TAX_RATE));
            await ShopCustomer.expects(vat.price).toHaveText(STANDARD_PRODUCT_TAX);
            await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toHaveText(TAXED_CART_TOTAL);
        },
    );

    test(
        'One invalid VAT ID keeps the cart taxed when a valid Dutch VAT ID is also stored.',
        { tag: ['@Checkout', '@Storefront'] },
        async ({
            IdProvider,
            ShopCustomer,
            TestDataService,
            StorefrontProductDetail,
            StorefrontCheckoutConfirm,
            Login,
            AddProductToCart,
            ProceedFromProductToCheckout,
        }) => {
            const customer = await TestDataService.createCustomer({
                accountType: 'business',
                company: `Intra EU Buyer ${IdProvider.getIdPair().id}`,
                vatIds: [DUTCH_VAT_ID, 'INVALID'],
                defaultBillingAddress: address('NL'),
                defaultShippingAddress: address('BE'),
            });
            const tax = await TestDataService.createTaxRate({ taxRate: 19 });
            const product = await TestDataService.createBasicProduct({}, tax.id);

            await ShopCustomer.attemptsTo(Login(customer));
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.attemptsTo(AddProductToCart(product, '2'));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            const vat = StorefrontCheckoutConfirm.getTaxSummary(PRODUCT_TAX_RATE);
            await ShopCustomer.expects(vat.label).toHaveText(StorefrontCheckoutConfirm.vatLabel(PRODUCT_TAX_RATE));
            await ShopCustomer.expects(vat.price).toHaveText(STANDARD_PRODUCT_TAX);
            await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toHaveText(TAXED_CART_TOTAL);
        },
    );

    test(
        'A private customer is charged VAT for a delivery to Belgium.',
        { tag: ['@Checkout', '@Storefront'] },
        async ({
            ShopCustomer,
            TestDataService,
            StorefrontProductDetail,
            StorefrontCheckoutConfirm,
            StorefrontCheckoutFinish,
            StorefrontAccountOrder,
            Login,
            AddProductToCart,
            ProceedFromProductToCheckout,
            ConfirmTermsAndConditions,
            SelectPaymentMethod,
            SelectShippingMethod,
            SubmitOrder,
        }) => {
            const customer = await TestDataService.createCustomer({
                accountType: 'private',
                vatIds: [DUTCH_VAT_ID],
                defaultBillingAddress: address('NL'),
                defaultShippingAddress: address('BE'),
            });
            const tax = await TestDataService.createTaxRate({ taxRate: 19 });
            const product = await TestDataService.createBasicProduct({}, tax.id);

            await ShopCustomer.attemptsTo(Login(customer));
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.attemptsTo(AddProductToCart(product, '2'));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            const vat = StorefrontCheckoutConfirm.getTaxSummary(PRODUCT_TAX_RATE);
            await ShopCustomer.expects(vat.label).toHaveText(StorefrontCheckoutConfirm.vatLabel(PRODUCT_TAX_RATE));
            await ShopCustomer.expects(vat.price).toHaveText(STANDARD_PRODUCT_TAX);
            await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toHaveText(TAXED_CART_TOTAL);

            await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
            await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));
            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
            await ShopCustomer.attemptsTo(SubmitOrder());
            const orderId = StorefrontCheckoutFinish.getOrderId();
            TestDataService.addCreatedRecord('order', orderId);
            const orderNumber = await StorefrontCheckoutFinish.getOrderNumber();
            await ShopCustomer.expects(StorefrontCheckoutFinish.taxPrice).toHaveText(STANDARD_PRODUCT_TAX);
            await ShopCustomer.expects(StorefrontCheckoutFinish.grandTotalPrice).toHaveText(TAXED_CART_TOTAL);

            await ShopCustomer.goesTo(StorefrontAccountOrder.url());
            const orderLocators = await StorefrontAccountOrder.getOrderByOrderNumber(orderNumber ?? '');
            await ShopCustomer.presses(orderLocators.orderDetailButton);
            await ShopCustomer.expects(orderLocators.taxPrice).toHaveText(STANDARD_PRODUCT_TAX);
            await ShopCustomer.expects(orderLocators.totalGross).toHaveText(TAXED_CART_TOTAL);
        },
    );

    test(
        'A delivery outside the EU stays tax-free for a commercial customer.',
        { tag: ['@Checkout', '@Storefront'] },
        async ({
            IdProvider,
            ShopCustomer,
            TestDataService,
            StorefrontProductDetail,
            StorefrontCheckoutConfirm,
            Login,
            AddProductToCart,
            ProceedFromProductToCheckout,
        }) => {
            const customer = await TestDataService.createCustomer({
                accountType: 'business',
                company: `Intra EU Buyer ${IdProvider.getIdPair().id}`,
                vatIds: [DUTCH_VAT_ID],
                defaultBillingAddress: address('NL'),
                defaultShippingAddress: address('CH'),
            });
            const tax = await TestDataService.createTaxRate({ taxRate: 19 });
            const product = await TestDataService.createBasicProduct({}, tax.id);

            await ShopCustomer.attemptsTo(Login(customer));
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.attemptsTo(AddProductToCart(product, '2'));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            const lineItem = StorefrontCheckoutConfirm.getLineItemByProductName(product.name);
            await ShopCustomer.expects(lineItem.taxPrice).toHaveText(StorefrontCheckoutConfirm.excludedVatText(TAX_FREE_PRICE));
            await ShopCustomer.expects(lineItem.productTotalPrice).toHaveText(TAX_FREE_CART_TOTAL);
            await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toHaveText(TAX_FREE_CART_TOTAL);
        },
    );

    test(
        'A delivery inside the shop country is charged VAT even with a Dutch VAT ID.',
        { tag: ['@Checkout', '@Storefront'] },
        async ({
            IdProvider,
            ShopCustomer,
            TestDataService,
            StorefrontProductDetail,
            StorefrontCheckoutConfirm,
            Login,
            AddProductToCart,
            ProceedFromProductToCheckout,
        }) => {
            const customer = await TestDataService.createCustomer({
                accountType: 'business',
                company: `Intra EU Buyer ${IdProvider.getIdPair().id}`,
                vatIds: [DUTCH_VAT_ID],
                defaultBillingAddress: address('NL'),
                defaultShippingAddress: address('DE'),
            });
            const tax = await TestDataService.createTaxRate({ taxRate: 19 });
            const product = await TestDataService.createBasicProduct({}, tax.id);

            await ShopCustomer.attemptsTo(Login(customer));
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.attemptsTo(AddProductToCart(product, '2'));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            const vat = StorefrontCheckoutConfirm.getTaxSummary(PRODUCT_TAX_RATE);
            await ShopCustomer.expects(vat.label).toHaveText(StorefrontCheckoutConfirm.vatLabel(PRODUCT_TAX_RATE));
            await ShopCustomer.expects(vat.price).toHaveText(STANDARD_PRODUCT_TAX);
            await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toHaveText(TAXED_CART_TOTAL);
        },
    );

    test(
        'A VAT ID of the shop country does not exempt a delivery to Belgium.',
        { tag: ['@Checkout', '@Storefront'] },
        async ({
            IdProvider,
            ShopCustomer,
            TestDataService,
            StorefrontProductDetail,
            StorefrontCheckoutConfirm,
            Login,
            AddProductToCart,
            ProceedFromProductToCheckout,
        }) => {
            const customer = await TestDataService.createCustomer({
                accountType: 'business',
                company: `Intra EU Buyer ${IdProvider.getIdPair().id}`,
                vatIds: [GERMAN_VAT_ID],
                defaultBillingAddress: address('DE'),
                defaultShippingAddress: address('BE'),
            });
            const tax = await TestDataService.createTaxRate({ taxRate: 19 });
            const product = await TestDataService.createBasicProduct({}, tax.id);

            await ShopCustomer.attemptsTo(Login(customer));
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.attemptsTo(AddProductToCart(product, '2'));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            const vat = StorefrontCheckoutConfirm.getTaxSummary(PRODUCT_TAX_RATE);
            await ShopCustomer.expects(vat.label).toHaveText(StorefrontCheckoutConfirm.vatLabel(PRODUCT_TAX_RATE));
            await ShopCustomer.expects(vat.price).toHaveText(STANDARD_PRODUCT_TAX);
            await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toHaveText(TAXED_CART_TOTAL);
        },
    );

    test(
        'A VAT ID of the shop country is tax-free when the goods leave the EU.',
        { tag: ['@Checkout', '@Storefront'] },
        async ({
            IdProvider,
            ShopCustomer,
            TestDataService,
            StorefrontProductDetail,
            StorefrontCheckoutConfirm,
            Login,
            AddProductToCart,
            ProceedFromProductToCheckout,
        }) => {
            const customer = await TestDataService.createCustomer({
                accountType: 'business',
                company: `Intra EU Buyer ${IdProvider.getIdPair().id}`,
                vatIds: [GERMAN_VAT_ID],
                defaultBillingAddress: address('DE'),
                defaultShippingAddress: address('CH'),
            });
            const tax = await TestDataService.createTaxRate({ taxRate: 19 });
            const product = await TestDataService.createBasicProduct({}, tax.id);

            await ShopCustomer.attemptsTo(Login(customer));
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.attemptsTo(AddProductToCart(product, '2'));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            const lineItem = StorefrontCheckoutConfirm.getLineItemByProductName(product.name);
            await ShopCustomer.expects(lineItem.taxPrice).toHaveText(StorefrontCheckoutConfirm.excludedVatText(TAX_FREE_PRICE));
            await ShopCustomer.expects(lineItem.productTotalPrice).toHaveText(TAX_FREE_CART_TOTAL);
            await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toHaveText(TAX_FREE_CART_TOTAL);
        },
    );

    test(
        'A VAT ID of the shop country is charged VAT for a delivery inside the shop country.',
        { tag: ['@Checkout', '@Storefront'] },
        async ({
            IdProvider,
            ShopCustomer,
            TestDataService,
            StorefrontProductDetail,
            StorefrontCheckoutConfirm,
            Login,
            AddProductToCart,
            ProceedFromProductToCheckout,
        }) => {
            const customer = await TestDataService.createCustomer({
                accountType: 'business',
                company: `Intra EU Buyer ${IdProvider.getIdPair().id}`,
                vatIds: [GERMAN_VAT_ID],
                defaultBillingAddress: address('DE'),
                defaultShippingAddress: address('DE'),
            });
            const tax = await TestDataService.createTaxRate({ taxRate: 19 });
            const product = await TestDataService.createBasicProduct({}, tax.id);

            await ShopCustomer.attemptsTo(Login(customer));
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.attemptsTo(AddProductToCart(product, '2'));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            const vat = StorefrontCheckoutConfirm.getTaxSummary(PRODUCT_TAX_RATE);
            await ShopCustomer.expects(vat.label).toHaveText(StorefrontCheckoutConfirm.vatLabel(PRODUCT_TAX_RATE));
            await ShopCustomer.expects(vat.price).toHaveText(STANDARD_PRODUCT_TAX);
            await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toHaveText(TAXED_CART_TOTAL);
        },
    );

    test(
        'A commercial customer without a VAT ID is tax-free when the goods leave the EU.',
        { tag: ['@Checkout', '@Storefront'] },
        async ({
            IdProvider,
            ShopCustomer,
            TestDataService,
            StorefrontProductDetail,
            StorefrontCheckoutConfirm,
            Login,
            AddProductToCart,
            ProceedFromProductToCheckout,
        }) => {
            const customer = await TestDataService.createCustomer({
                accountType: 'business',
                company: `Intra EU Buyer ${IdProvider.getIdPair().id}`,
                defaultBillingAddress: address('DE'),
                defaultShippingAddress: address('CH'),
            });
            const tax = await TestDataService.createTaxRate({ taxRate: 19 });
            const product = await TestDataService.createBasicProduct({}, tax.id);

            await ShopCustomer.attemptsTo(Login(customer));
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.attemptsTo(AddProductToCart(product, '2'));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            const lineItem = StorefrontCheckoutConfirm.getLineItemByProductName(product.name);
            await ShopCustomer.expects(lineItem.taxPrice).toHaveText(StorefrontCheckoutConfirm.excludedVatText(TAX_FREE_PRICE));
            await ShopCustomer.expects(lineItem.productTotalPrice).toHaveText(TAX_FREE_CART_TOTAL);
            await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toHaveText(TAX_FREE_CART_TOTAL);
        },
    );

    test(
        'A commercial customer without a VAT ID is charged VAT for a delivery inside the shop country.',
        { tag: ['@Checkout', '@Storefront'] },
        async ({
            IdProvider,
            ShopCustomer,
            TestDataService,
            StorefrontProductDetail,
            StorefrontCheckoutConfirm,
            Login,
            AddProductToCart,
            ProceedFromProductToCheckout,
        }) => {
            const customer = await TestDataService.createCustomer({
                accountType: 'business',
                company: `Intra EU Buyer ${IdProvider.getIdPair().id}`,
                defaultBillingAddress: address('DE'),
                defaultShippingAddress: address('DE'),
            });
            const tax = await TestDataService.createTaxRate({ taxRate: 19 });
            const product = await TestDataService.createBasicProduct({}, tax.id);

            await ShopCustomer.attemptsTo(Login(customer));
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.attemptsTo(AddProductToCart(product, '2'));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            const vat = StorefrontCheckoutConfirm.getTaxSummary(PRODUCT_TAX_RATE);
            await ShopCustomer.expects(vat.label).toHaveText(StorefrontCheckoutConfirm.vatLabel(PRODUCT_TAX_RATE));
            await ShopCustomer.expects(vat.price).toHaveText(STANDARD_PRODUCT_TAX);
            await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toHaveText(TAXED_CART_TOTAL);
        },
    );
});
