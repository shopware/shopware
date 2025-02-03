import { test } from '@fixtures/AcceptanceTest';
import { FlowConfig } from '@shopware-ag/acceptance-test-suite';

test('As an admin user, I want to create a new flow', { tag: '@Flow' }, async ({
    ShopAdmin,
    AdminFlowBuilderListing,
    IdProvider,
    TestDataService,
    CreateFlow,
    CheckFlow,

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
        await ShopAdmin.attemptsTo(CheckFlow(testConfig as FlowConfig));
    });
});
