import { test } from '@fixtures/AcceptanceTest';

test ('As a merchant, I can perform bulk edits on customer information', { tag: '@BulkEdits' }, async ({
    TestDataService,
    ShopAdmin,
    AdminCustomerListing,
    AdminCustomerDetail,
    BulkEditCustomers,
    IdProvider,
    DefaultSalesChannel,
    InstanceMeta,
}) => {

    test.skip(InstanceMeta.features['V6_7_0_0'], 'This test is incompatible with V6_7_0_0, ticket: NEXT-40150 ');

    const customer1 = await TestDataService.createCustomer();
    const customer2 = await TestDataService.createCustomer();
    const customer3 = await TestDataService.createCustomer();
    const currentCustomerGroup = (await TestDataService.getCustomerGroupById(customer3.groupId));
    const customerGroupToUpdate = await TestDataService.createCustomerGroup();
    const currentLanguage = await TestDataService.getLanguageById(customer3.languageId);
    const languageToUpdate = await TestDataService.getLanguageData('de-DE');
    await TestDataService.assignSalesChannelLanguage(DefaultSalesChannel.salesChannel.id, languageToUpdate.id);
    const tagName1 = `001_bulk_edit_${customer1.id}`;
    const tagName2 = `002_bulk_edit_${customer2.id}`;
    await TestDataService.createTag(tagName1);
    await TestDataService.createTag(tagName2);
    const tagData = {
        changeType: 'Overwrite',
        tags: [tagName1, tagName2],
    }
    const accountData = {
        customerGroup: customerGroupToUpdate.name,
        accountStatus: false,
        language: languageToUpdate.name,
    }
    const customFieldSetId = IdProvider.getIdPair().uuid;
    const customFieldSetName = `cmf_set_${IdProvider.getIdPair().id}`;
    const customFieldTextName = `cmf_set_text_${IdProvider.getIdPair().id}`;
    const customFieldValue = `cmf_value_${IdProvider.getIdPair().id}`;
    const customFieldData = {
        customFieldSetName: customFieldSetName,
        customFieldName: customFieldTextName,
        customFieldValue: customFieldValue,
    }

    await test.step('Prepares a custom field set', async () => {
        await TestDataService.createCustomFieldSet({ id: customFieldSetId, name: customFieldSetName });
        await TestDataService.createCustomField(customFieldSetId, { name: customFieldTextName, config: {
            label: {
                'en-GB': customFieldTextName,
            },
        }});
    });

    await test.step('Merchant bulk edits two customers', async () => {
        await ShopAdmin.goesTo(AdminCustomerListing.url());
        const customers = [customer1, customer2];
        await ShopAdmin.attemptsTo(BulkEditCustomers(customers, accountData, tagData, customFieldData));
    });

    await test.step('Verifies the changes applied for bulk edited customers', async () => {
        for (const customer of [customer1, customer2]) {
            //verify general information
            await ShopAdmin.goesTo(AdminCustomerDetail.url(customer.id));
            const userCustomerGroup = await AdminCustomerDetail.getCustomerGroup();
            await ShopAdmin.expects(userCustomerGroup).toHaveText(accountData.customerGroup);
            const accountStatus = await AdminCustomerDetail.getAccountStatus();
            await ShopAdmin.expects(accountStatus).toHaveText(accountData.accountStatus? 'Active': 'Inactive');
            const language = await AdminCustomerDetail.getLanguage();
            await ShopAdmin.expects(language).toHaveText(accountData.language);
            //verify tags
            const tags = await AdminCustomerDetail.tagList.textContent();
            ShopAdmin.expects(tags).toContain(tagName1);
            ShopAdmin.expects(tags).toContain(tagName2);
            ShopAdmin.expects(await AdminCustomerDetail.tagItems.all()).toHaveLength(2);
            //Verify custom field
            const customFieldSetTabContent = await AdminCustomerDetail.getCustomFieldSetCardContentByName(customFieldSetName);
            await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTab).toBeVisible();
            await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTab).toHaveText(customFieldSetName);
            await customFieldSetTabContent.customFieldSetTab.click();
            await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTabCustomContent).toBeVisible();
            await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTabCustomContent.getByText(customFieldTextName)).toBeVisible();
            await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTabCustomContent.locator(`#${customFieldTextName}`)).toHaveValue(customFieldValue);
        }
    });

    await test.step('Verify that changes are not applied to other customers', async () => {
        await ShopAdmin.goesTo(AdminCustomerDetail.url(customer3.id));
        const userCustomerGroup = await AdminCustomerDetail.getCustomerGroup();
        await ShopAdmin.expects(userCustomerGroup).toHaveText(currentCustomerGroup.name);
        const accountStatus = await AdminCustomerDetail.getAccountStatus();
        await ShopAdmin.expects(accountStatus).toHaveText(customer3.active? 'Active': 'Inactive');
        const language = await AdminCustomerDetail.getLanguage();
        await ShopAdmin.expects(language).toHaveText(currentLanguage.name);
        ShopAdmin.expects(await AdminCustomerDetail.tagItems.all()).toHaveLength(0);
        const customFieldSetTabContent = await AdminCustomerDetail.getCustomFieldSetCardContentByName(customFieldSetName);
        await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTab).toBeVisible();
        await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTab).toHaveText(customFieldSetName);
        await customFieldSetTabContent.customFieldSetTab.click();
        await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTabCustomContent).toBeVisible();
        await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTabCustomContent.getByText(customFieldTextName)).toBeVisible();
        await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTabCustomContent.locator(`#${customFieldTextName}`)).toHaveValue('');
    });

});
