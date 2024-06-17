import { test, expect } from '@fixtures/AcceptanceTest';

test('Shop customer should be able to see the uploaded image in the storefront. @product', async ({
    ShopCustomer,
    IdProvider,
    AdminApiContext,
    MediaData,
    ProductData,
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

    // Add image to product
    const productId = ProductData.id;
    const productMediaId = IdProvider.getIdPair().uuid;
    const editProductResponse = await AdminApiContext.patch(`./product/${productId}`, {
        data: {
            coverId: productMediaId,
            media: [
                {
                    id: productMediaId,
                    media: {
                        id: MediaData.id,
                    },
                },
            ],
        },
    });
    expect(editProductResponse.ok()).toBeTruthy();

    await ShopCustomer.attemptsTo(Login());

    await test.step('Logged-In shop customer should be able to see the cover image on the product listing page.', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.expects(StorefrontHome.productImages.getByAltText(`alt-${MediaData.id}`)).toBeVisible();
    });

    await test.step('Logged-In shop customer should be able to see an image on the product detail page.', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(ProductData));
        await ShopCustomer.expects(StorefrontProductDetail.productSingleImage.getByAltText(`alt-${MediaData.id}`)).toBeVisible();
    });

    await test.step('Logged-In shop customer should be able to see the cover image in the offcanvas.', async () => {
        await ShopCustomer.attemptsTo(AddProductToCart(ProductData));
        await ShopCustomer.expects(StorefrontProductDetail.offCanvasLineItemImages.getByAltText(`alt-${MediaData.id}`)).toBeVisible();
    });

    await test.step('Logged-In shop customer should be able to see the cover image on the checkout confirm page.', async () => {
        await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
        await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
        await ShopCustomer.attemptsTo(SelectInvoicePaymentOption());
        await ShopCustomer.attemptsTo(SelectStandardShippingOption());
        await ShopCustomer.expects(StorefrontCheckoutConfirm.cartLineItemImages.getByAltText(`alt-${MediaData.id}`)).toBeVisible();
    });

    await test.step('Logged-In shop customer should be able to see the cover image on the checkout finish page.', async () => {
        await ShopCustomer.attemptsTo(SubmitOrder());
        await ShopCustomer.expects(StorefrontCheckoutFinish.cartLineItemImages.getByAltText(`alt-${MediaData.id}`)).toBeVisible();
    });

    await test.step('Logged-In shop customer should be able to see the cover image within the account order page.', async () => {
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        await StorefrontAccountOrder.orderExpandButton.click();
        await ShopCustomer.expects(StorefrontAccountOrder.cartLineItemImages.getByAltText(`alt-${MediaData.id}`)).toBeVisible();
    });

    await ShopCustomer.attemptsTo(Logout());

    await test.step('Shop customer should be able to see the cover image on the checkout register page.', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(ProductData));
        await ShopCustomer.attemptsTo(AddProductToCart(ProductData));
        await ShopCustomer.goesTo(StorefrontCheckoutRegister.url());
        await ShopCustomer.expects(StorefrontCheckoutRegister.cartLineItemImages.getByAltText(`alt-${MediaData.id}`)).toBeVisible();
    });

    await test.step('Shop customer should be able to see the cover image on the cart page.', async () => {
        await ShopCustomer.goesTo(StorefrontCheckoutCart.url());
        await ShopCustomer.expects(StorefrontCheckoutCart.cartLineItemImages.getByAltText(`alt-${MediaData.id}`)).toBeVisible();
    });

    await test.step('Shop customer should be able to see the cover image in search suggest page.', async () => {
        await ShopCustomer.attemptsTo(OpenSearchSuggestPage(ProductData.name));
        await ShopCustomer.expects(StorefrontSearchSuggest.searchSuggestLineItemImages.getByAltText(`alt-${MediaData.id}`)).toBeVisible();
    });

    await test.step('Shop customer should be able to see the cover image on the search result page.', async () => {
        await ShopCustomer.attemptsTo(OpenSearchResultPage(ProductData.name));
        await ShopCustomer.expects(StorefrontSearch.productImages.getByAltText(`alt-${MediaData.id}`)).toBeVisible();
    });
});
