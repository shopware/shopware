import {expect, test} from '@fixtures/AcceptanceTest';

test('As an admin, I want to create documents and make sure they contain certain infos.', { tag: '@Documents' }, async ({
    ShopAdmin,
    TestDataService,
    DefaultSalesChannel,
    AdminDocumentListing,
    AdminDocumentDetail,
    AdminOrderDetail,
    ShopCustomer,
    StorefrontAccountOrder,
    StorefrontAccountLogin,
    Login,

    }) => {

    const product = await TestDataService.createBasicProduct();
    const order = await TestDataService.createOrder([{ product, quantity: 1 }], DefaultSalesChannel.customer);


    await test.step('Go to documents settings page and activate documents in customer accounts', async () => {
        await ShopAdmin.goesTo(AdminDocumentListing.url());
        await AdminDocumentListing.invoiceLink.click();
        await ShopAdmin.expects(AdminDocumentDetail.page.getByText('Document type Invoice')).toBeVisible();
        await AdminDocumentDetail.showInAccountSwitch.click();
        await AdminDocumentDetail.saveButton.click();
        });
    await test.step('Go to order detail page and create credit item', async () => {
        await ShopAdmin.goesTo(AdminOrderDetail.url(order.id, 'general'));
        await AdminDocumentDetail.page.locator('.sw-button-group').locator('.sw-context-button').click();
        await ShopAdmin.expects(AdminDocumentDetail.page.locator('.sw-context-menu')).toBeVisible();
        await AdminDocumentDetail.page.locator('.sw-context-menu').locator('.sw-order-line-items-grid__can-create-discounts-button').click();
        await ShopAdmin.expects(AdminDocumentDetail.page.locator('.sw-data-grid__row--1')).toBeVisible();
        await AdminDocumentDetail.page.locator('.sw-data-grid__row--0').dblclick();
        await AdminDocumentDetail.page.locator('.sw-data-grid__row--0').getByRole('textbox').nth(0).fill('Credit item');
        await AdminDocumentDetail.page.locator('.sw-data-grid__row--0').getByRole('textbox').nth(1).fill('10');
        await AdminDocumentDetail.page.locator('.sw-data-grid__row--0').getByRole('button', { name: 'Save' }).click();
        await ShopAdmin.expects(AdminDocumentDetail.page.locator('.sw-data-grid__row--0').getByRole('button', { name: 'Save' })).not.toBeVisible();
        await ShopAdmin.expects(AdminDocumentDetail.page.locator('.sw-data-grid__row--0')).not.toContainText('Credit item');
        //await ShopAdmin.expects(AdminDocumentDetail.page.locator('.sw-data-grid__row--1')).toContainText('Credit item');
        //await ShopAdmin.expects(AdminDocumentDetail.page.locator('.sw-notifications__info')).toBeVisible();
        await AdminDocumentDetail.saveButton.click();
        await ShopAdmin.expects(AdminDocumentDetail.page.locator('.sw-order-detail__alert')).not.toBeVisible();
        await ShopAdmin.expects(AdminDocumentDetail.page.getByRole('button').filter({ hasText: 'Save' }).locator('.mt-button__loader')).not.toBeVisible();
        });
    await test.step('Go to documents tab and create invoice', async () => {
        await ShopAdmin.goesTo(AdminOrderDetail.url(order.id, 'documents'));
        await ShopAdmin.expects(AdminOrderDetail.page.locator('.sw-empty-state')).toContainText('No documents created yet');
        await AdminOrderDetail.page.getByRole('button', { name: 'Create new document' }).click();
        await ShopAdmin.expects(AdminDocumentDetail.page.locator('.sw-modal__body')).toContainText('Invoice');
        await AdminDocumentDetail.page.locator('.sw-modal__body').getByRole('radio', { name: 'Invoice', exact: true }).click();
        await AdminDocumentDetail.page.locator('.sw-modal__footer').getByRole('button', { name: 'Create document' }).click();
        await ShopAdmin.expects(AdminDocumentDetail.page.locator('.sw-modal__header')).toHaveText('Create document - Invoice');
        //await ShopAdmin.expects(AdminDocumentDetail.page.locator('.sw-context-button__menu-position')).toBeVisible();
        await AdminDocumentDetail.page.getByTestId('mt-icon__regular-chevron-down-xs').nth(1).click();
        await ShopAdmin.expects(AdminDocumentDetail.page.locator('.sw-context-menu')).toBeVisible();
        await AdminDocumentDetail.page.locator('.sw-context-menu-item__text').getByText('Create and send').click();
        await ShopAdmin.expects(AdminDocumentDetail.page.locator('.mt-button__loader')).not.toBeVisible();
        await ShopAdmin.expects(AdminDocumentDetail.page.locator('.sw-modal__header')).toHaveText('Send document');
        await AdminDocumentDetail.page.locator('.sw-modal__footer').getByRole('button', { name: 'Send document' }).click();
        await ShopAdmin.expects(AdminDocumentDetail.page.locator('.mt-button__loader')).not.toBeVisible();
        });
    await test.step('Go to customer account in Storefront and check the order document', async () => {
        await ShopCustomer.goesTo(StorefrontAccountLogin.url());
        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());


    });
});
