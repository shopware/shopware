import { test, formatPrice } from '@fixtures/AcceptanceTest';

test('Promotion with rule applied to newsletter recipient should be able to create on admin.',
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
        SubcribeToNewsletter,
        AddProductToCart,
        ProceedFromCartToCheckout,
        ConfirmTermsAndConditions,
        SubmitOrder,
    }) => {
        // add rule with condition "customer is newsletter recipient"
        let ruleConfig = { ruleId: IdProvider.getIdPair().uuid };
        await ShopAdmin.attemptsTo(CreateRuleNewsletterRecipient(ruleConfig));

        // add promotion with discount percentage and condition "customer is newsletter recipient"
        const discountPercentage = 10, promotionName = 'Newsletter Recipient Promotion';

        let promotionConfig = {
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
            { currencyId: SalesChannelBaseConfig.defaultCurrencyId, gross: productGrossPrice, linked: true, net: 42.016806722689 }
        ];
        const product = await TestDataService.createBasicProduct({ price: productPrices, purchasePrices: productPrices });

        // subscribe newsletter recipient with same email as customer and subscribe to newsletter
        const recipientConfig = { email: DefaultSalesChannel.customer.email };
        await ShopCustomer.attemptsTo(SubcribeToNewsletter(recipientConfig));

        // login as customer
        await ShopCustomer.attemptsTo(Login(DefaultSalesChannel.customer));

        // add product to cart
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.expects(StorefrontProductDetail.productSinglePrice).toContainText(formatPrice(productGrossPrice));
        await ShopCustomer.attemptsTo(AddProductToCart(product));

        // validate product price 
        const offcanvasItem = await StorefrontOffCanvasCart.getLineItemByProductNumber(product.productNumber);
        await ShopCustomer.expects(offcanvasItem.productTotalPriceValue).toContainText(formatPrice(productGrossPrice));

        // validate promotion is applied in offcanvas cart and discount is visible with correct amount, then validate discounted price in cart
        const promoItem = StorefrontOffCanvasCart.page.locator('.line-item-promotion', { hasText: promotionName });
        const promoLabel = promoItem.locator('.line-item-label');
        const promoPrice = promoItem.locator('.line-item-total-price-value');
        await ShopCustomer.expects(promoLabel).toContainText(promotionName);
        await ShopCustomer.expects(promoPrice).toContainText(formatPrice(discountValue));
        await ShopCustomer.expects(StorefrontOffCanvasCart.subTotalPrice).toContainText(formatPrice(discountPrice));
        await ShopCustomer.attemptsTo(ProceedFromCartToCheckout());

        // check if promotion code is automatically applied and discount is visible on confirmation page, then validate discounted price in confirmation page
        const confirmProductTable = StorefrontCheckoutConfirm.page.locator('.confirm-product');
        const productLineItem = confirmProductTable.locator('.line-item-product');
        const productNameLabel = productLineItem.locator('.line-item-label');
        const productTotalPrice = productLineItem.locator('.line-item-total-price-value');
        await ShopCustomer.expects(productNameLabel).toContainText(product.translated.name);
        await ShopCustomer.expects(productTotalPrice).toContainText(formatPrice(productGrossPrice));

        const promotionLineItems = confirmProductTable.locator('.line-item-promotion');
        const promotionLineItem = promotionLineItems.filter({ hasText: promotionName });
        const promotionLabel = promotionLineItem.locator('.line-item-label');
        await promotionLabel.scrollIntoViewIfNeeded();
        await ShopCustomer.expects(promotionLabel).toContainText(promotionName);
        await ShopCustomer.expects(StorefrontCheckoutConfirm.grandTotalPrice).toContainText(formatPrice(discountPrice));

        await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());

        await ShopCustomer.attemptsTo(SubmitOrder());
        await ShopCustomer.expects(StorefrontCheckoutFinish.page.getByText(promotionName)).toBeVisible();
        await ShopCustomer.expects(StorefrontCheckoutFinish.grandTotalPrice).toContainText(formatPrice(discountPrice));

        const orderId = StorefrontCheckoutFinish.getOrderId();

        TestDataService.addCreatedRecord('order', orderId);

    }
);
