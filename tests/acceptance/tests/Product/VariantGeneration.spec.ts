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

    await shopAdmin.expects(adminProductDetailPage.page.getByRole('link', { name: 'Medium - Red -' })).toBeVisible();
    await shopAdmin.expects(adminProductDetailPage.page.getByRole('link', { name: 'Large - Red -' })).toBeVisible();
    await shopAdmin.expects(adminProductDetailPage.page.getByRole('link', { name: 'Medium - Blue -' })).toBeVisible();
    await shopAdmin.expects(adminProductDetailPage.page.getByRole('link', { name: 'Large - Blue -' })).toBeVisible();
});
