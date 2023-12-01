import { test } from '@fixtures/AcceptanceTest';

test('Shop administrator should be able to create product variants. @product', async ({
    shopAdmin,
    propertiesData,
    productData,
    adminProductDetailPage,
    GenerateVariants,
}) => {
    await shopAdmin.goesTo(adminProductDetailPage);
    await shopAdmin.page.waitForLoadState('domcontentloaded');

    await shopAdmin.attemptsTo(GenerateVariants());

    await shopAdmin.expects(adminProductDetailPage.page.getByText('Red Medium')).toBeVisible();
    await shopAdmin.expects(adminProductDetailPage.page.getByText('Red Large')).toBeVisible();
    await shopAdmin.expects(adminProductDetailPage.page.getByText('Blue Medium')).toBeVisible();
    await shopAdmin.expects(adminProductDetailPage.page.getByText('Blue Large')).toBeVisible();
});
