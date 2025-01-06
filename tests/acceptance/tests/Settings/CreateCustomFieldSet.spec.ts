import { test, expect } from '@fixtures/AcceptanceTest';

test('As a merchant, I want to create custom fields use it in categories, products and customers.', { tag: '@Settings' }, async ({
    ShopAdmin,
    AdminCategoryDetail,
    AdminProductDetail,
    AdminRuleCreate,
    AdminCustomerDetail,
    AdminCustomFieldCreate,
    AdminCustomFieldDetail,
    AdminCustomFieldListing,
    TestDataService,
    IdProvider,
    DefaultSalesChannel,
    CreateCustomField,
    AdminManufacturerDetail,
    InstanceMeta,
}) => {

    test.skip(InstanceMeta.features['V6_7_0_0'], 'This test is incompatible with V6_7_0_0: ticket: NEXT-40151 and NEXT-40150');

    const product = await TestDataService.createBasicProduct();
    const customer = await TestDataService.createCustomer();
    const manufacturer = await TestDataService.createBasicManufacturer();

    const customFieldSetName = `custom_field_set_${IdProvider.getIdPair().id}`;
    const customFieldTextName = `custom_field_set_text_${IdProvider.getIdPair().id}`;
    const customFieldNumberName = `custom_field_set_number_${IdProvider.getIdPair().id}`;

    await ShopAdmin.goesTo(AdminCustomFieldCreate.url());
    await AdminCustomFieldCreate.technicalNameInput.fill(customFieldSetName);
    await AdminCustomFieldCreate.positionInput.fill('1');
    await AdminCustomFieldCreate.assignToSelectionList.click();
    await AdminCustomFieldCreate.resultAssignToPopoverItemList.getByText('Products').click();
    await AdminCustomFieldCreate.resultAssignToPopoverItemList.getByText('Categories').click();
    await AdminCustomFieldCreate.resultAssignToPopoverItemList.getByText('Customers').click();

    const responsePromise = AdminCustomFieldCreate.page.waitForResponse('**/api/search/custom-field-set');
    await AdminCustomFieldCreate.saveButton.click();
    const customFieldSetResponse = await responsePromise;
    expect(customFieldSetResponse).toBeTruthy();

    await ShopAdmin.attemptsTo(CreateCustomField(customFieldTextName, 'text'));
    await ShopAdmin.attemptsTo(CreateCustomField(customFieldNumberName, 'number'));

    const url = AdminCustomFieldDetail.page.url();
    const customFieldSetUuid = url.split('/')[url.split('/').length -1];
    TestDataService.addCreatedRecord('custom_field_set', customFieldSetUuid);

    await test.step('Validate the availability of custom field on custom field listing page.', async () => {

        await ShopAdmin.goesTo(AdminCustomFieldListing.url());
        const customFieldSetLineItemName = await AdminCustomFieldListing.getLineItemByCustomFieldSetName(customFieldSetName);
        await ShopAdmin.expects(customFieldSetLineItemName.customFieldSetNameText).toHaveText(customFieldSetName);
    });

    await test.step('Validate the availability of custom field within category detail page.', async () => {

        await ShopAdmin.goesTo(AdminCategoryDetail.url(DefaultSalesChannel.salesChannel.navigationCategoryId));
        await ShopAdmin.expects(AdminCategoryDetail.customFieldCard).toBeVisible();
        const customFieldSetTabContent = await AdminCategoryDetail.getCustomFieldSetCardContentByName(customFieldSetName);
        await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTab).toBeVisible();
        await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTab).toHaveText(customFieldSetName);
        await customFieldSetTabContent.customFieldSetTab.click();
        await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTabCustomContent).toBeVisible();
        await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTabCustomContent.getByLabel(customFieldTextName)).toBeVisible();
        await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTabCustomContent.getByLabel(customFieldNumberName)).toBeVisible();
    });

    await test.step('Validate the availability of custom field within product detail page.', async () => {

        await ShopAdmin.goesTo(AdminProductDetail.url(product.id));
        await AdminProductDetail.specificationsTabLink.click();
        await ShopAdmin.expects(AdminProductDetail.customFieldCard).toBeVisible();
        const customFieldSetTabContent = await AdminProductDetail.getCustomFieldSetCardContentByName(customFieldSetName);
        await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTab).toBeVisible();
        await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTab).toHaveText(customFieldSetName);
        await customFieldSetTabContent.customFieldSetTab.click();
        await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTabCustomContent).toBeVisible();
        await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTabCustomContent.getByLabel(customFieldTextName)).toBeVisible();
        await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTabCustomContent.getByLabel(customFieldNumberName)).toBeVisible();
    });

    await test.step('Validate the availability of custom field within customer detail page.', async () => {

        await ShopAdmin.goesTo(AdminCustomerDetail.url(customer.id));
        await ShopAdmin.expects(AdminCustomerDetail.customFieldCard).toBeVisible();
        const customFieldSetTabContent = await AdminCustomerDetail.getCustomFieldSetCardContentByName(customFieldSetName);
        await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTab).toBeVisible();
        await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTab).toHaveText(customFieldSetName);
        await customFieldSetTabContent.customFieldSetTab.click();
        await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTabCustomContent).toBeVisible();
        await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTabCustomContent.getByLabel(customFieldTextName)).toBeVisible();
        await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTabCustomContent.getByLabel(customFieldNumberName)).toBeVisible();
    });

    await test.step('Validate the unavailability of custom field within manufacturer page.', async () => {

        await ShopAdmin.goesTo(AdminManufacturerDetail.url(manufacturer.id));
        await ShopAdmin.expects(AdminManufacturerDetail.customFieldCard).not.toBeVisible();
    });

    await test.step('Validate the availability of custom field within rule builder page.', async () => {

        await ShopAdmin.goesTo(AdminRuleCreate.url());
        await AdminRuleCreate.conditionTypeSelectionInput.click();
        await AdminRuleCreate.filtersResultPopoverSelectionList.filter({ hasText: 'Customer with custom field' }).click();
        await AdminRuleCreate.conditionValueSelectionInput.click();
        await ShopAdmin.expects(AdminRuleCreate.filtersResultPopoverSelectionList.filter({ hasText: customFieldSetName })).toHaveCount(2);
        await ShopAdmin.expects(AdminRuleCreate.filtersResultPopoverSelectionList.getByText(customFieldTextName+' '+customFieldSetName)).toBeVisible();
        await ShopAdmin.expects(AdminRuleCreate.filtersResultPopoverSelectionList.getByText(customFieldNumberName+' '+customFieldSetName)).toBeVisible();
    });
});
