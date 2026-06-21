import { test, formatPrice } from '@fixtures/AcceptanceTest';

test('Registered newsletter recipient should have corresponding promotion applied automatically.',
    { tag: ['@Checkout', '@Storefront'] },
    async ({
        IdProvider,
        ShopAdmin,
        DefaultSalesChannel,
        TestDataService,
        ShopCustomer,
        SalesChannelBaseConfig,
        StorefrontProductDetail,
        StorefrontOffCanvasCart,
        StorefrontCheckoutConfirm,
        StorefrontCheckoutFinish,
        Login,
        CreateRuleNewsletterRecipient,
        AddPromotionWithConditionRule,
        AddProductToCart,
        ProceedFromCartToCheckout,
        ConfirmTermsAndConditions,
        SelectPaymentMethod,
        SelectShippingMethod,
        SubmitOrder,
    }) => {
        const ruleConfig = { ruleId: IdProvider.getIdPair().uuid };
        await ShopAdmin.attemptsTo(CreateRuleNewsletterRecipient(ruleConfig));

        // add promotion with discount percentage and condition "customer is newsletter recipient"
        const discountPercentage = 10, promotionName = 'Newsletter Recipient Promotion';
        const promotionConfig = {
            id: IdProvider.getIdPair().uuid,
            name: `${promotionName} ${ruleConfig.ruleId}`,
            useCode: false,
            discountValue: discountPercentage,
            discountScope: 'cart',
            discountType: 'percentage',
            salesChannelId: DefaultSalesChannel.salesChannel.id,
            ruleId: ruleConfig.ruleId,
        };
        await ShopAdmin.attemptsTo(AddPromotionWithConditionRule(promotionConfig));

        const productGrossPrice = 50, discountPrice = 45.00, discountValue = 5.00;
        const productPrices = [
            { currencyId: DefaultSalesChannel.salesChannel.currencyId, gross: productGrossPrice, linked: true, net: 42.016806722689 },
            { currencyId: SalesChannelBaseConfig.defaultCurrencyId, gross: productGrossPrice, linked: true, net: 42.016806722689 },
        ];
        const product = await TestDataService.createBasicProduct({ price: productPrices, purchasePrices: productPrices });

        await ShopCustomer.attemptsTo(Login(DefaultSalesChannel.customer));
        await TestDataService.createNewsletterRecipient(DefaultSalesChannel.customer);

        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.expects(StorefrontProductDetail.productSinglePrice).toContainText(formatPrice(productGrossPrice));
        await ShopCustomer.attemptsTo(AddProductToCart(product));

        const offcanvasItem = await StorefrontOffCanvasCart.getLineItemByProductNumber(product.productNumber);
        await ShopCustomer.expects(offcanvasItem.productTotalPriceValue).toContainText(formatPrice(productGrossPrice));

        const promoItem = await StorefrontOffCanvasCart.getLineItemByPromotionName(promotionName);
        await ShopCustomer.expects(promoItem.promotionLabel).toContainText(promotionName);
        await ShopCustomer.expects(promoItem.promotionPrice).toContainText(formatPrice(discountValue));
        await ShopCustomer.expects(StorefrontOffCanvasCart.subTotalPrice).toContainText(formatPrice(discountPrice));
        await ShopCustomer.attemptsTo(ProceedFromCartToCheckout());
        await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
        await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));

        const productLineItem = StorefrontCheckoutConfirm.getLineItemByProductName(StorefrontCheckoutConfirm.productLineItems, product.translated.name);
        await ShopCustomer.expects(productLineItem.productNameLabel).toContainText(product.translated.name);
        await ShopCustomer.expects(productLineItem.productTotalPrice).toContainText(formatPrice(productGrossPrice));

        const promotionLineItem = StorefrontCheckoutConfirm.getLineItemByProductName(StorefrontCheckoutConfirm.promotionLineItems, promotionName);
        await ShopCustomer.expects(promotionLineItem.productNameLabel).toContainText(promotionName);
        await ShopCustomer.expects(promotionLineItem.productTotalPrice).toContainText(formatPrice(discountValue));
        await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toContainText(formatPrice(discountPrice));

        await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
        await ShopCustomer.attemptsTo(SubmitOrder());
        await ShopCustomer.expects(StorefrontCheckoutFinish.page.getByText(promotionName)).toBeVisible();
        await ShopCustomer.expects(StorefrontCheckoutFinish.grandTotalPrice).toContainText(formatPrice(discountPrice));
        const orderId = StorefrontCheckoutFinish.getOrderId();
        TestDataService.addCreatedRecord('order', orderId);
    }
);
