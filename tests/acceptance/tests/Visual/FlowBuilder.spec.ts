import { test, expect } from '@fixtures/AcceptanceTest';
import { FlowConfig } from '@shopware-ag/acceptance-test-suite';
import { setViewport, replaceElements, hideElements } from '@shopware-ag/acceptance-test-suite';

test('Visual: Administration Flow Builder', { tag: '@Visual' }, async ({
    ShopAdmin,
    TestDataService,
    IdProvider,
    AdminFlowBuilderListing,
    AdminFlowBuilderDetail,
    CreateFlow,
}) => {


    const uniqueId = IdProvider.getIdPair().uuid;
    const tagName= (`Test tag - ${uniqueId}`);
    const flowName = (`Test flow - ${uniqueId}`)
    await TestDataService.createTag(tagName);
    const testConfig = {
        name: flowName,
        description: 'This flow is being created to test the creation of flows.',
        priority: '1',
        active: true,
        triggerSearchTerm: 'placed',
        triggerLabel: 'Checkout / Order / Placed',
        condition: 'Customers from USA',
        trueAction: 'Send email',
        trueActionIdentifier: 'Order confirmation',
        falseAction: 'Add tag',
        falseActionIdentifier: tagName,
    }

    await test.step('Create a screenshot of a flow general tab.', async () => {
        await ShopAdmin.goesTo(AdminFlowBuilderListing.url());
        await ShopAdmin.attemptsTo(CreateFlow(testConfig as FlowConfig));
        await ShopAdmin.expects(AdminFlowBuilderDetail.page.locator('.sw-skeleton').first()).not.toBeVisible();
        await ShopAdmin.expects(AdminFlowBuilderDetail.page.locator('.mt-banner--positive')).toBeVisible();
        await AdminFlowBuilderDetail.page.locator('.mt-banner__close').click();
        await setViewport(AdminFlowBuilderDetail.page, {
            //requestURL: 'api/search/sales-channel-type',
        })
        await replaceElements(AdminFlowBuilderDetail.page, [
            AdminFlowBuilderDetail.header,
        ]);
        await hideElements(AdminFlowBuilderDetail.page, [
            AdminFlowBuilderDetail.nameField,
        ]);
        await expect(AdminFlowBuilderDetail.contentView).toHaveScreenshot({
        });
    });

    await test.step('Create a screenshot of the actual flow.', async () => {
        await AdminFlowBuilderDetail.flowTab.click();
        await ShopAdmin.expects(AdminFlowBuilderDetail.triggerSelectField).toBeVisible();
        await setViewport(AdminFlowBuilderDetail.page, {
           //requestURL: 'api/search/sales-channel-type',
        });
        await replaceElements(AdminFlowBuilderDetail.page, [
            AdminFlowBuilderDetail.header,
            AdminFlowBuilderDetail.actionContentTag,
        ]);
        await expect(AdminFlowBuilderDetail.contentView).toHaveScreenshot({
        });
    });
});
