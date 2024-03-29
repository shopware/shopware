import { test } from '@fixtures/AcceptanceTest';
import { expect } from '@playwright/test';

test('Shop customer should be able to see the uploaded image in the storefront. @product', async ({
    shopCustomer,
    mediaData,
    productData,
    productDetailPage,
    adminApiContext,
    idProvider,
    storefrontHomePage,
    checkoutCartPage,
    checkoutRegisterPage,
    checkoutConfirmPage,
    checkoutFinishPage,
    AddProductToCart,
    ProceedFromProductToCheckout,
    ConfirmTermsAndConditions,
    SelectInvoicePaymentOption,
    SelectStandardShippingOption,
    SubmitOrder,
    searchPage,
    searchSuggestPage,
    OpenSearchResultPage,
    OpenSearchSuggestPage,
    accountOrderPage,
    Login,
    Logout,
 }) => {

    test.info().annotations.push({
        type: 'Acceptance Criteria',
        description: 'Logged-In shop customer should be able to see the cover image on the product listing page.',
    }, {
        type: 'Acceptance Criteria',
        description: 'Logged-In shop customer should be able to see an image on the product detail page.',
    }, {
        type: 'Acceptance Criteria',
        description: 'Logged-In shop customer should be able to see the cover image in the offcanvas.',
    }, {
        type: 'Acceptance Criteria',
        description: 'Logged-In shop customer should be able to see the cover image on the checkout confirm page.',
    }, {
        type: 'Acceptance Criteria',
        description: 'Logged-In shop customer should be able to see the cover image on the checkout finish page.',
    }, {
        type: 'Acceptance Criteria',
        description: 'Logged-In shop customer should be able to see the cover image within the account order page.',
    }, {
        type: 'Acceptance Criteria',
        description: 'Shop customer should be able to see the cover image on the checkout register page.',
    }, {
        type: 'Acceptance Criteria',
        description: 'Shop customer should be able to see the cover image on the cart page.',
    }, {
        type: 'Acceptance Criteria',
        description: 'Shop customer should be able to see the cover image in search suggest page.',
    }, {
        type: 'Acceptance Criteria',
        description: 'Shop customer should be able to see the cover image on the search result page.',
    });

    // Add image to product
    const productId = productData.id;
    const productMediaId = idProvider.getIdPair().uuid;
    const editProductResponse = await adminApiContext.patch(`./product/${productId}`, {
        data: {
            coverId: productMediaId,
            media: [
                {
                    id: productMediaId,
                    media: {
                        id: mediaData.id,
                    },
                },
            ],
        },
    });
    await expect(editProductResponse.ok()).toBeTruthy();
    await shopCustomer.attemptsTo(Login());

    await test.step('Logged-In shop customer should be able to see the cover image on the product listing page.', async () => {
        await shopCustomer.goesTo(storefrontHomePage);
        await shopCustomer.expects(storefrontHomePage.productImages.getByAltText(`alt-${mediaData.id}`)).toBeVisible();
    });

    await test.step('Logged-In shop customer should be able to see an image on the product detail page.', async () => {
        await shopCustomer.goesTo(productDetailPage);
        await shopCustomer.expects(productDetailPage.productSingleImage.getByAltText(`alt-${mediaData.id}`)).toBeVisible();
    });

    await test.step('Logged-In shop customer should be able to see the cover image in the offcanvas.', async () => {
        await shopCustomer.attemptsTo(AddProductToCart(productData));
        await shopCustomer.expects(productDetailPage.offCanvasLineItemImages.getByAltText(`alt-${mediaData.id}`)).toBeVisible();
    });

    await test.step('Logged-In shop customer should be able to see the cover image on the checkout confirm page.', async () => {
        await shopCustomer.attemptsTo(ProceedFromProductToCheckout());
        await shopCustomer.attemptsTo(ConfirmTermsAndConditions());
        await shopCustomer.attemptsTo(SelectInvoicePaymentOption());
        await shopCustomer.attemptsTo(SelectStandardShippingOption());
        await shopCustomer.expects(checkoutConfirmPage.cartLineItemImages.getByAltText(`alt-${mediaData.id}`)).toBeVisible();
    });

    await test.step('Logged-In shop customer should be able to see the cover image on the checkout finish page.', async () => {
        await shopCustomer.attemptsTo(SubmitOrder());
        await shopCustomer.expects(checkoutFinishPage.cartLineItemImages.getByAltText(`alt-${mediaData.id}`)).toBeVisible();
    });

    await test.step('Logged-In shop customer should be able to see the cover image within the account order page.', async () => {
        await shopCustomer.goesTo(accountOrderPage);
        await accountOrderPage.orderExpandButton.click();
        await shopCustomer.expects(accountOrderPage.cartLineItemImages.getByAltText(`alt-${mediaData.id}`)).toBeVisible();
    });

    await shopCustomer.attemptsTo(Logout());

    await test.step('Shop customer should be able to see the cover image on the checkout register page.', async () => {
        await shopCustomer.goesTo(productDetailPage);
        await shopCustomer.attemptsTo(AddProductToCart(productData));
        await shopCustomer.goesTo(checkoutRegisterPage);
        await shopCustomer.expects(checkoutRegisterPage.cartLineItemImages.getByAltText(`alt-${mediaData.id}`)).toBeVisible();
    });

    await test.step('Shop customer should be able to see the cover image on the cart page.', async () => {
        await shopCustomer.goesTo(checkoutCartPage);
        await shopCustomer.expects(checkoutCartPage.cartLineItemImages.getByAltText(`alt-${mediaData.id}`)).toBeVisible();
    });

    await test.step('Shop customer should be able to see the cover image in search suggest page.', async () => {
        await shopCustomer.attemptsTo(OpenSearchSuggestPage(productData.name));
        await shopCustomer.expects(searchSuggestPage.searchSuggestLineItemImages.getByAltText(`alt-${mediaData.id}`)).toBeVisible();
    });

    await test.step('Shop customer should be able to see the cover image on the search result page.', async () => {
        await shopCustomer.attemptsTo(OpenSearchResultPage(productData.name));
        await shopCustomer.expects(searchPage.productImages.getByAltText(`alt-${mediaData.id}`)).toBeVisible();
    });
});
