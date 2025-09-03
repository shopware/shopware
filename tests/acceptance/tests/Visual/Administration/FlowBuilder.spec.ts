import { test, expect } from '@fixtures/AcceptanceTest';
import { FlowConfig } from '@shopware-ag/acceptance-test-suite';
import { setViewport, replaceElements, hideElements } from '@shopware-ag/acceptance-test-suite';

test('Visual: Flow Builder listing', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminFlowBuilderListing,
}) => {
    await test.step('Create a screenshot of the flow listing.', async () => {
        await ShopAdmin.goesTo(AdminFlowBuilderListing.url());
        await setViewport(AdminFlowBuilderListing.page, {
            waitForSelector: AdminFlowBuilderListing.createFlowButton,
        })
        await replaceElements(AdminFlowBuilderListing.page, [
            AdminFlowBuilderListing.testFlowNameCells,
            ]);
        await AdminFlowBuilderListing.flowTemplatesTab.hover();
        await expect(AdminFlowBuilderListing.contentView).toHaveScreenshot('Flow-Builder-Listing.png', {
        });
    });

    await test.step('Create a screenshot of the flow templates listing.', async () => {
        await AdminFlowBuilderListing.flowTemplatesTab.click();
        await setViewport(AdminFlowBuilderListing.page, {
            waitForSelector: AdminFlowBuilderListing.pagination,
        })
        await expect(AdminFlowBuilderListing.contentView).toHaveScreenshot('Flow-Builder-Templates-Listing.png', {
        });
    });
});

test('Visual: Flow Builder detail page', { tag: '@Visual' }, async ({
    ShopAdmin,
    TestDataService,
    IdProvider,
    AdminFlowBuilderListing,
    AdminFlowBuilderDetail,
    CreateFlow,
}) => {

    const uniqueId = IdProvider.getIdPair().uuid;
    const tagName = (`Test tag - ${uniqueId}`);
    const flowName = (`Test flow - ${uniqueId}`);
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
        await ShopAdmin.expects(AdminFlowBuilderDetail.skeletonLoader.first()).not.toBeVisible();
        await ShopAdmin.expects(AdminFlowBuilderDetail.successMessage).toBeVisible();
        await AdminFlowBuilderDetail.messageClose.click();
        await setViewport(AdminFlowBuilderDetail.page, {
            waitForSelector: AdminFlowBuilderDetail.activeSwitch,
        })
        await replaceElements(AdminFlowBuilderDetail.page, [
            AdminFlowBuilderDetail.header,
        ]);
        await hideElements(AdminFlowBuilderDetail.page, [
            AdminFlowBuilderDetail.nameField,
        ]);
        await expect(AdminFlowBuilderDetail.contentView).toHaveScreenshot('Flow-Builder-Detail-General-Tab.png', {
        });
    });

    await test.step('Create a screenshot of the actual flow.', async () => {
        await AdminFlowBuilderDetail.flowTab.click();
        await ShopAdmin.expects(AdminFlowBuilderDetail.triggerSelectField).toBeVisible();
        await setViewport(AdminFlowBuilderDetail.page, {
            waitForSelector: AdminFlowBuilderDetail.falseBlock,
        });
        await replaceElements(AdminFlowBuilderDetail.page, [
            AdminFlowBuilderDetail.header,
            AdminFlowBuilderDetail.actionContentTag,
        ]);
        await expect(AdminFlowBuilderDetail.contentView).toHaveScreenshot('Flow-Builder-Detail-Flow-Tab.png', {
        });
    });
});
