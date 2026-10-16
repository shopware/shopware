import { test, expect } from '@fixtures/AcceptanceTest';

const placedOrderIds: string[] = [];

// Documents generated for the placed order would block its deletion (ON DELETE RESTRICT) in the TestDataService cleanup.
test.afterEach(async ({ AdminApiContext }) => {
    const orderIds = placedOrderIds.splice(0);
    if (orderIds.length === 0) {
        return;
    }

    const response = await AdminApiContext.post('search-ids/document', {
        data: { filter: [{ type: 'equalsAny', field: 'orderId', value: orderIds }] },
    });
    expect(response.ok()).toBeTruthy();
    const { data: documentIds } = (await response.json()) as { data: string[] };

    // eslint-disable-next-line playwright/no-conditional-in-test
    if (documentIds.length > 0) {
        const deleteResponse = await AdminApiContext.post('_action/sync', {
            data: {
                'delete-documents': {
                    entity: 'document',
                    action: 'delete',
                    payload: documentIds.map((id) => ({ id })),
                },
            },
        });
        expect(deleteResponse.ok()).toBeTruthy();
    }
});

test('The Storefront should implement accessibility best practices.', { tag: ['@Accessibility', '@Storefront'] }, async ({
    ShopCustomer,
    TestDataService,
    ValidateAccessibility,
    Login,
    AddProductToCart,
    ProceedFromCartToCheckout,
    ConfirmTermsAndConditions,
    SubmitOrder,
    StorefrontCheckoutFinish,
    StorefrontProductDetail,
    StorefrontCategory,
    StorefrontAccountLogin,
    StorefrontAccount,
    StorefrontAccountOrder,
    StorefrontCheckoutCart,
    StorefrontAccountProfile,
    StorefrontAccountAddresses,
    StorefrontAccountPayment,
    StorefrontPageNotFound,
}) => {
    test.slow();

    const product = await TestDataService.createBasicProduct();
    const category = await TestDataService.createCategory();
    await TestDataService.assignProductCategory(product.id, category.id);

    await test.step('Login Page Accessibility', async () => {
        await ShopCustomer.goesTo(StorefrontAccountLogin.url());
        await ShopCustomer.attemptsTo(ValidateAccessibility('Login', true));
    });

    await test.step('Account Page Accessibility', async () => {
        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontAccount.url());
        await ShopCustomer.attemptsTo(ValidateAccessibility('Account', true));
    });

    await test.step('Category Page Accessibility', async () => {
        await ShopCustomer.goesTo(StorefrontCategory.url(category.name));

        // Category has one valid violation, because first level headline can be configured via CMS.
        await ShopCustomer.attemptsTo(ValidateAccessibility('Category', 1));
    });

    await test.step('Product Detail Page Accessibility', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.attemptsTo(ValidateAccessibility('Product', true));
    });

    await test.step('Cart Page Accessibility', async () => {
        await ShopCustomer.attemptsTo(AddProductToCart(product, '5'));
        await ShopCustomer.goesTo(StorefrontCheckoutCart.url());
        await ShopCustomer.attemptsTo(ValidateAccessibility('Cart', true));
    });

    await test.step('Checkout Accessibility', async () => {
        await ShopCustomer.attemptsTo(ProceedFromCartToCheckout());
        await ShopCustomer.attemptsTo(ValidateAccessibility('Checkout Confirm', true));

        await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
        await ShopCustomer.attemptsTo(SubmitOrder());
        const orderId = StorefrontCheckoutFinish.getOrderId();
        placedOrderIds.push(orderId);
        TestDataService.addCreatedRecord('order', orderId);

        await ShopCustomer.attemptsTo(ValidateAccessibility('Checkout Finish', true));
    });

    await test.step('Account Order Accessibility', async () => {
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        await ShopCustomer.attemptsTo(ValidateAccessibility('Account Order', true));
    });

    await test.step('Account Profile Accessibility', async () => {
        await ShopCustomer.goesTo(StorefrontAccountProfile.url());
        await ShopCustomer.attemptsTo(ValidateAccessibility('Account Profile', true));
    });

    await test.step('Account Addresses Accessibility', async () => {
        await ShopCustomer.goesTo(StorefrontAccountAddresses.url());
        await ShopCustomer.attemptsTo(ValidateAccessibility('Account Addresses', true));
    });

    await test.step('Account Payment Accessibility', async () => {
        await ShopCustomer.goesTo(StorefrontAccountPayment.url());
        await ShopCustomer.attemptsTo(ValidateAccessibility('Account Payment', true));
    });

    await test.step('Page Not Found Accessibility', async () => {
        await ShopCustomer.goesTo(StorefrontPageNotFound.url());
        await ShopCustomer.attemptsTo(ValidateAccessibility('Page Not Found', true));
    });
});
