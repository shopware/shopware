import { test } from '@fixtures/AcceptanceTest';

test('Shop customer should be able to see the product image in the Storefront.', { tag: '@Product' }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontProductDetail,
    StorefrontHome,
    StorefrontCheckoutCart,
    StorefrontCheckoutRegister,
    StorefrontCheckoutConfirm,
    StorefrontCheckoutFinish,
    StorefrontSearch,
    StorefrontSearchSuggest,
    StorefrontAccountOrder,
    AddProductToCart,
    ProceedFromProductToCheckout,
    ConfirmTermsAndConditions,
    SelectInvoicePaymentOption,
    SelectStandardShippingOption,
    SubmitOrder,
    OpenSearchResultPage,
    OpenSearchSuggestPage,
    Login,
    Logout,
}) => {
    const product = await TestDataService.createBasicProduct();
    const media = await TestDataService.createMediaPNG();

    await TestDataService.assignProductMedia(product.id, media.id);

    await ShopCustomer.attemptsTo(Login());

    await test.step('Logged-In shop customer should be able to see the cover image on the product listing page.', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.expects(StorefrontHome.productImages.getByAltText(media.alt)).toBeVisible();
    });

    await test.step('Logged-In shop customer should be able to see an image on the product detail page.', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.expects(StorefrontProductDetail.productSingleImage.getByAltText(media.alt)).toBeVisible();
    });

    await test.step('Logged-In shop customer should be able to see the cover image in the offcanvas.', async () => {
        await ShopCustomer.attemptsTo(AddProductToCart(product));
        await ShopCustomer.expects(StorefrontProductDetail.offCanvasLineItemImages.getByAltText(media.alt)).toBeVisible();
    });

    await test.step('Logged-In shop customer should be able to see the cover image on the checkout confirm page.', async () => {
        await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
        await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
        await ShopCustomer.attemptsTo(SelectInvoicePaymentOption());
        await ShopCustomer.attemptsTo(SelectStandardShippingOption());
        await ShopCustomer.expects(StorefrontCheckoutConfirm.cartLineItemImages.getByAltText(media.alt)).toBeVisible();
    });

    await test.step('Logged-In shop customer should be able to see the cover image on the checkout finish page.', async () => {
        await ShopCustomer.attemptsTo(SubmitOrder());
        await ShopCustomer.expects(StorefrontCheckoutFinish.cartLineItemImages.getByAltText(media.alt)).toBeVisible();

        const orderId = StorefrontCheckoutFinish.getOrderId();
        TestDataService.addCreatedRecord('order', orderId);
    });

    await test.step('Logged-In shop customer should be able to see the cover image within the account order page.', async () => {
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        await StorefrontAccountOrder.orderExpandButton.click();
        await ShopCustomer.expects(StorefrontAccountOrder.cartLineItemImages.getByAltText(media.alt)).toBeVisible();
    });

    await ShopCustomer.attemptsTo(Logout());

    await test.step('Shop customer should be able to see the cover image on the checkout register page.', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.attemptsTo(AddProductToCart(product));
        await ShopCustomer.goesTo(StorefrontCheckoutRegister.url());
        await ShopCustomer.expects(StorefrontCheckoutRegister.cartLineItemImages.getByAltText(media.alt)).toBeVisible();
    });

    await test.step('Shop customer should be able to see the cover image on the cart page.', async () => {
        await ShopCustomer.goesTo(StorefrontCheckoutCart.url());
        await ShopCustomer.expects(StorefrontCheckoutCart.cartLineItemImages.getByAltText(media.alt)).toBeVisible();
    });

    await test.step('Shop customer should be able to see the cover image in search suggest page.', async () => {
        await ShopCustomer.attemptsTo(OpenSearchSuggestPage(product.name));
        await ShopCustomer.expects(StorefrontSearchSuggest.searchSuggestLineItemImages.getByAltText(media.alt)).toBeVisible();
    });

    await test.step('Shop customer should be able to see the cover image on the search result page.', async () => {
        await ShopCustomer.attemptsTo(OpenSearchResultPage(product.name));
        await ShopCustomer.expects(StorefrontSearch.productImages.getByAltText(media.alt)).toBeVisible();
    });
});
