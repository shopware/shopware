import { test } from '@fixtures/AcceptanceTest';
import { FlowConfig } from '@shopware-ag/acceptance-test-suite';

test('As an admin user, I want to create a new flow', { tag: '@Flow' }, async ({
    ShopAdmin,
    AdminFlowBuilderListing,
    AdminFlowBuilderDetail,
    IdProvider,
    TestDataService,
    CreateFlow,

}) => {

    const uniqueId = IdProvider.getIdPair().uuid;
    const tagName= (`Test tag - ${uniqueId}`);
    const flowName = (`Test flow - ${uniqueId}`)
    await TestDataService.createTag(tagName);
    const testConfig = {
        //general information
        name: flowName,
        description: 'This flow is being created to test the creation of flows.',
        priority: '1',
        active: true,
        //trigger
        triggerSearchTerm: 'placed',
        triggerLabel: 'Checkout / Order / Placed',
        //condition
        condition: 'Customers from USA',
        //../actions
        trueAction: 'Send email',
        trueActionIdentifier: 'Order confirmation',
        falseAction: 'Add tag',
        falseActionIdentifier: tagName,
    }

    await test.step('Create a flow with a condition and two actions.', async () => {
        await ShopAdmin.goesTo(AdminFlowBuilderListing.url());
        await ShopAdmin.attemptsTo(CreateFlow(testConfig as FlowConfig));
    });

    await test.step('Confirm the flow exists and is structured correctly.', async () => {
        await ShopAdmin.goesTo(AdminFlowBuilderListing.url());
        const flowListingRow = await AdminFlowBuilderListing.getLineItemByFlowName(`${testConfig.name}`);
        await ShopAdmin.expects(flowListingRow.flowActiveCheckmark).toBeVisible();
        await flowListingRow.flowContextMenuButton.click();
        await flowListingRow.contextMenuEdit.click();
        // Confirm that general tab has the correct values
        await ShopAdmin.expects(AdminFlowBuilderDetail.nameField).toHaveValue(`${testConfig.name}`);
        await ShopAdmin.expects(AdminFlowBuilderDetail.descriptionField).toHaveValue(`${testConfig.description}`);
        await ShopAdmin.expects(AdminFlowBuilderDetail.priorityField).toHaveValue(`${testConfig.priority}`);
        // Confirm the flow's structure
        await AdminFlowBuilderDetail.flowTab.click();
        // Check trigger by assessing the tooltip that appears on hover
        const trigger = await AdminFlowBuilderDetail.getSelectedTrigger();
        ShopAdmin.expects(trigger).toEqual(`${testConfig.triggerLabel}`);
        // Make sure there is only one condition
        await ShopAdmin.expects(AdminFlowBuilderDetail.conditionRule).toHaveText(`${testConfig.condition}`);
        // Make sure there is only one section
        await ShopAdmin.expects(AdminFlowBuilderDetail.sequenceSeparator).toBeVisible();
        // Make sure there are only desired actions present after the condition
        await ShopAdmin.expects(AdminFlowBuilderDetail.trueBlockActionDescription).toContainText(`${testConfig.trueActionIdentifier}`);
        await ShopAdmin.expects(AdminFlowBuilderDetail.falseBlockActionDescription).toContainText(`${testConfig.falseActionIdentifier}`);
    });
});
