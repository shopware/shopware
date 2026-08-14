import { test, formatPrice, type Product } from '@fixtures/AcceptanceTest';

const promotionName = 'Newsletter Recipient Promotion';
const productGrossPrice = 50;
const discountPrice = 45.0;
const discountValue = 5.0;
const discountPercentage = 10;

test.describe('Newsletter recipient promotion', () => {
    let product: Product;
    let guestCustomerEmail = '';
    let submittedOrderId = '';

    test.beforeEach(async ({ IdProvider, DefaultSalesChannel, TestDataService, SalesChannelBaseConfig }) => {
        submittedOrderId = '';
        guestCustomerEmail = `${IdProvider.getIdPair().uuid}@test.com`;
        const ruleId = IdProvider.getIdPair().uuid;
        const ruleConfig = {
            id: ruleId,
            name: `Test-Rule - ${ruleId}`,
            description: 'This rule applied for newsletter recipients',
        };
        const ruleCondition = { type: 'customerIsNewsletterRecipient', value: { isNewsletterRecipient: true } };
        await TestDataService.createBasicRule(ruleConfig, ruleCondition);

        const promotionConfig = {
            id: IdProvider.getIdPair().uuid,
            name: `${promotionName} ${ruleConfig.id}`,
            useCode: false,
            discountValue: discountPercentage,
            discountScope: 'cart',
            discountType: 'percentage',
            salesChannelId: DefaultSalesChannel.salesChannel.id,
            ruleId: ruleConfig.id,
        };
        await TestDataService.createPromotionWithConditionRule(promotionConfig);

        const productPrices = [
            {
                currencyId: DefaultSalesChannel.salesChannel.currencyId,
                gross: productGrossPrice,
                linked: true,
                net: 42.016806722689,
            },
            {
                currencyId: SalesChannelBaseConfig.defaultCurrencyId,
                gross: productGrossPrice,
                linked: true,
                net: 42.016806722689,
            },
        ];
        product = await TestDataService.createBasicProduct({ price: productPrices, purchasePrices: productPrices });
    });

    [
        'Guest',
        'Registered',
    ].forEach((customerType) => {
        test(
            `${customerType} customer newsletter recipient should have corresponding promotion applied automatically.`,
            {
                tag: [
                    '@Checkout',
                    '@Storefront',
                ],
            },
            async ({
                IdProvider,
                DefaultSalesChannel,
                TestDataService,
                ShopCustomer,
                StorefrontProductDetail,
                StorefrontOffCanvasCart,
                StorefrontAccountLogin,
                StorefrontCheckoutConfirm,
                StorefrontCheckoutFinish,
                Login,
                Register,
                AddProductToCart,
                SelectPaymentMethod,
                SelectShippingMethod,
            }) => {
                const customer =
                    customerType === 'Guest'
                        ? { ...DefaultSalesChannel.customer, id: IdProvider.getIdPair().uuid, email: guestCustomerEmail }
                        : DefaultSalesChannel.customer;

                if (customerType === 'Registered') {
                    await ShopCustomer.attemptsTo(Login(customer));
                }
                await TestDataService.createNewsletterRecipient(customer);
                // Guest has no customer context yet; promo applies only after checkout registration.
                await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
                await ShopCustomer.expects(StorefrontProductDetail.productSinglePrice).toContainText(
                    formatPrice(productGrossPrice),
                );
                await ShopCustomer.attemptsTo(AddProductToCart(product));

                const offcanvasItem = await StorefrontOffCanvasCart.getLineItemByProductNumber(product.productNumber);
                await ShopCustomer.expects(offcanvasItem.productTotalPriceValue).toContainText(
                    formatPrice(productGrossPrice),
                );

                if (customerType === 'Registered') {
                    const promoItem = await StorefrontOffCanvasCart.getLineItemByPromotionName(promotionName);
                    await ShopCustomer.expects(promoItem.promotionLabel).toContainText(promotionName);
                    await ShopCustomer.expects(promoItem.promotionPrice).toContainText(formatPrice(discountValue));
                    await ShopCustomer.expects(StorefrontOffCanvasCart.subTotalPrice).toContainText(
                        formatPrice(discountPrice),
                    );
                }

                await ShopCustomer.presses(StorefrontOffCanvasCart.goToCheckoutButton);
                if (customerType === 'Guest') {
                    await StorefrontAccountLogin.page.waitForURL('**/checkout/register', { waitUntil: 'commit' });
                    await ShopCustomer.expects(StorefrontAccountLogin.registerEmailInput).toBeVisible();
                    await ShopCustomer.attemptsTo(Register({ email: customer.email, isGuest: true }));
                }
                await StorefrontCheckoutConfirm.page.waitForURL('**/checkout/confirm', { waitUntil: 'commit' });
                await ShopCustomer.expects(StorefrontCheckoutConfirm.headline).toBeVisible();

                await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
                await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));

                const productLineItem = StorefrontCheckoutConfirm.getLineItemByProductName(product.translated.name);
                await ShopCustomer.expects(productLineItem.productNameLabel).toContainText(product.translated.name);
                await ShopCustomer.expects(productLineItem.productTotalPrice).toContainText(formatPrice(productGrossPrice));

                const promotionLineItem = StorefrontCheckoutConfirm.getLineItemByPromotionName(promotionName);
                await ShopCustomer.expects(promotionLineItem.promotionNameLabel).toContainText(promotionName);
                await ShopCustomer.expects(promotionLineItem.promotionTotalPrice).toContainText(formatPrice(discountValue));
                await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toContainText(
                    formatPrice(discountPrice),
                );

                await StorefrontCheckoutConfirm.termsAndConditionsCheckbox.check();
                await ShopCustomer.expects(StorefrontCheckoutConfirm.termsAndConditionsCheckbox).toBeChecked();
                await StorefrontCheckoutConfirm.submitOrderButton.click();
                await ShopCustomer.expects(StorefrontCheckoutFinish.headline).toBeVisible();
                await ShopCustomer.expects(StorefrontCheckoutFinish.grandTotalPrice).toContainText(
                    formatPrice(discountPrice),
                );

                submittedOrderId = StorefrontCheckoutFinish.getOrderId();
            },
        );
    });

    test.afterEach(async ({ TestDataService }) => {
        if (submittedOrderId) {
            TestDataService.addCreatedRecord('order', submittedOrderId);
        }
    });
});
