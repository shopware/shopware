import { test } from '@fixtures/AcceptanceTest';

test('As an admin user, I want to create a new flow', { tag: '@Flow' }, async ({

       ShopAdmin,
       AdminFlowBuilderListing,
       AdminFlowBuilderCreate,
       AdminFlowBuilderDetail,
       IdProvider,
       TestDataService,

    }) => {
    test.setTimeout(30_000);

    const uniqueId = IdProvider.getIdPair().uuid;
    const customTag= (`Test tag - ${uniqueId}`);
    const flowName = (`Test flow - ${uniqueId}`)
    await TestDataService.createTag(customTag);

    // GIVEN the admin user is logged in
    await ShopAdmin.goesTo(AdminFlowBuilderListing.url());
    // WHEN the user creates a new flow in the Flow Builder
    await AdminFlowBuilderListing.createFlowButton.click();
    await ShopAdmin.expects(AdminFlowBuilderCreate.page.locator('.smart-bar__header')).toHaveText('New flow');
    await AdminFlowBuilderCreate.nameField.fill(flowName);
    await AdminFlowBuilderCreate.page.locator('.sw-flow-detail-general__general-description').getByLabel('Description').fill('This flow is created to test the creation of flows.');
    await AdminFlowBuilderCreate.page.locator('.sw-flow-detail-general__general-priority').getByLabel('Priority').fill('1');
    await AdminFlowBuilderCreate.page.locator('.sw-flow-detail-general__general-active').getByLabel('Active').click();
    await AdminFlowBuilderCreate.flowTab.click();
    // AND defines a trigger
    await AdminFlowBuilderCreate.triggerSelectField.fill('placed');
    await AdminFlowBuilderCreate.triggerSelectField.press('Enter');
    // AND adds a condition
    await AdminFlowBuilderCreate.page.locator('.sw-flow-sequence').getByText('Add condition (IF)').click();
    await AdminFlowBuilderCreate.page.locator('.sw-entity-single-select').getByRole('textbox').click();
    await ShopAdmin.expects(AdminFlowBuilderCreate.page.locator('.sw-select-result-list__item-list')).toBeVisible();
    await AdminFlowBuilderCreate.page.locator('.sw-select-result-list__content').getByRole('listitem').filter({ hasText: 'USA' }).click();
    // AND adds actions for both branches of the condition
    await AdminFlowBuilderCreate.page.locator('.sw-flow-sequence__true-block').getByText('Add action (THEN)').click();
    await AdminFlowBuilderCreate.page.locator('.sw-flow-sequence__true-block').locator('.sw-single-select').click();
    await ShopAdmin.expects(AdminFlowBuilderCreate.page.locator('.sw-select-result-list__item-list')).toBeVisible();
    await AdminFlowBuilderCreate.page.locator('.sw-select-result-list__content').getByRole('listitem').filter({ hasText: 'Send email' }).click();
    await ShopAdmin.expects(AdminFlowBuilderCreate.page.locator('.sw-flow-mail-send-modal')).toBeVisible();
    await AdminFlowBuilderCreate.page.locator('.sw-entity-single-select__selection').click();
    await ShopAdmin.expects(AdminFlowBuilderCreate.page.locator('.sw-select-result-list__item-list')).toBeVisible();
    await AdminFlowBuilderCreate.page.locator('.sw-select-result-list__content').getByRole('listitem').filter({ hasText: 'Order confirmation' }).click();
    await AdminFlowBuilderCreate.page.locator('.sw-flow-mail-send-modal').locator('.sw-button--primary').getByText('Add action').click();
    await ShopAdmin.expects(AdminFlowBuilderCreate.page.locator('.sw-flow-sequence__true-block').locator('.sw-flow-sequence-action__action-description')).toContainText('Template: Order confirmation');
    await ShopAdmin.expects(AdminFlowBuilderCreate.page.locator('.sw-flow-sequence__false-block')).toBeVisible();
    await AdminFlowBuilderCreate.page.locator('.sw-flow-sequence__false-block').getByText('Add action (THEN)').click();
    await ShopAdmin.expects(AdminFlowBuilderCreate.page.locator('.sw-flow-sequence__false-block').locator('.sw-flow-sequence-action__select')).toBeVisible();
    await AdminFlowBuilderCreate.page.locator('.sw-flow-sequence__false-block').locator('.sw-single-select').click();
    await ShopAdmin.expects(AdminFlowBuilderCreate.page.locator('.sw-select-result-list__item-list')).toBeVisible();
    await AdminFlowBuilderCreate.page.locator('.sw-select-result-list__content').getByRole('listitem').filter({ hasText: 'Add tag' }).click();
    await ShopAdmin.expects(AdminFlowBuilderCreate.page.locator('.sw-modal__title')).toHaveText('Add tag');
    await AdminFlowBuilderCreate.page.locator('.sw-select__selection').getByLabel('Tags').click();
    await ShopAdmin.expects(AdminFlowBuilderCreate.page.locator('.sw-select-result-list__item-list')).toBeVisible();
    await AdminFlowBuilderCreate.page.locator('.sw-select-result-list__content').getByRole('listitem').filter({ hasText: `${customTag}` }).click();
    await AdminFlowBuilderCreate.page.locator('.sw-modal__dialog').locator('.sw-button--primary').getByText('Add action').click();
    await ShopAdmin.expects(AdminFlowBuilderCreate.page.locator('.sw-flow-sequence__false-block').locator('.sw-flow-sequence-action__actions')).toContainText(`Tag: ${customTag}`);
    // THEN the user can save the flow correctly
    await AdminFlowBuilderCreate.saveButton.click();
    await ShopAdmin.expects(AdminFlowBuilderDetail.page.locator('.sw-alert__title')).toHaveText('Success');
    // AND validate that the defined flow is structured correctly
    await ShopAdmin.goesTo(AdminFlowBuilderListing.url());
    const flowListingRow = await AdminFlowBuilderListing.getLineItemByFlowName(flowName);
    await ShopAdmin.expects(flowListingRow.flowActiveCheckmark).toBeVisible();
    await flowListingRow.flowContextMenuButton.click();
    await AdminFlowBuilderListing.contextMenuEdit.click();
    await ShopAdmin.expects(AdminFlowBuilderDetail.page.locator('.sw-flow-detail-general__general-name').getByLabel('Name')).toHaveValue(flowName);
    await ShopAdmin.expects(AdminFlowBuilderDetail.page.locator('.sw-flow-detail-general__general-description').getByLabel('Description')).toHaveValue('This flow is created to test the creation of flows.');
    await ShopAdmin.expects(AdminFlowBuilderDetail.page.locator('.sw-flow-detail-general__general-priority').getByLabel('Priority')).toHaveValue('1');
    await AdminFlowBuilderDetail.flowTab.click();
    await ShopAdmin.expects(AdminFlowBuilderDetail.page.locator('.sw-flow-detail-flow__trigger-card').getByRole('textbox')).toBeVisible();
    await AdminFlowBuilderDetail.page.locator('.sw-flow-detail-flow__trigger-card').getByRole('textbox').hover();
    const tooltip = await AdminFlowBuilderDetail.page.waitForSelector('.sw-tooltip');
    const tooltipText = await tooltip.innerText();
    await ShopAdmin.expects(tooltipText).toEqual('Checkout / Order / Placed');
    await ShopAdmin.expects(AdminFlowBuilderDetail.page.locator('.sw-flow-sequence-condition__rule-header')).toHaveText('Customers from USA');
    await ShopAdmin.expects(AdminFlowBuilderDetail.page.locator('.sw-flow-delay-action__delay_card')).not.toBeVisible();
    await ShopAdmin.expects(AdminFlowBuilderDetail.page.locator('.sw-flow-detail-flow__position-connection')).toBeVisible();
    await ShopAdmin.expects(AdminFlowBuilderDetail.page.locator('.sw-flow-sequence__true-block').locator('.sw-flow-sequence-action__action-description')).toContainText('Template: Order confirmation');
    await ShopAdmin.expects(AdminFlowBuilderDetail.page.locator('.sw-flow-sequence__false-block').locator('.sw-flow-sequence-action__action-description')).toContainText(`Tag: ${customTag}`);
});
