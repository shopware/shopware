import { test, expect } from '@fixtures/AcceptanceTest';
import { FlowConfig } from '@shopware-ag/acceptance-test-suite';

test(
    'As an admin user, I want to create a new flow',
    {
        tag: '@Flow',
        annotation: {
            type: 'issue',
            description: 'https://github.com/shopware/shopware/issues/15749',
        },
    },
    async ({ ShopAdmin, AdminFlowBuilderListing, AdminFlowBuilderDetail, IdProvider, TestDataService, CreateFlow }) => {
        // This flow spans ~15 sequential admin interactions; on a loaded/shared environment the
        // default timeouts are too tight and the very first assertion inside CreateFlow (waiting
        // for the create-flow page to render) can time out before navigation actually completes.
        test.slow();

        const uniqueId = IdProvider.getIdPair().uuid;
        const tagName = `Test tag - ${uniqueId}`;
        const flowName = `Test flow - ${uniqueId}`;
        await TestDataService.createTag(tagName);
        const testConfig = {
            name: flowName,
            description: 'This flow is being created to test the creation of flows.',
            priority: '1',
            active: true,
            triggerSearchTerm: 'placed',
            triggerLabel: 'Checkout / Order / Placed',
            condition: 'Shopping cart / Order with digital products',
            trueAction: 'Send email',
            trueActionIdentifier: 'Order confirmation',
            falseAction: 'Add tag',
            falseActionIdentifier: tagName,
        };

        await test.step('Create a flow with a condition and two actions.', async () => {
            // The create-flow button occasionally doesn't navigate on the first click (its own
            // assertion for the "New flow" header uses a fixed 5s timeout that test.slow() does not
            // extend). Nothing is persisted until CreateFlow reaches the save step, so retrying the
            // whole block - re-navigating and re-clicking - is safe and recovers from a missed click.
            await expect(async () => {
                await ShopAdmin.goesTo(AdminFlowBuilderListing.url());
                await ShopAdmin.expects(AdminFlowBuilderListing.createFlowButton).toBeEnabled();
                await ShopAdmin.attemptsTo(CreateFlow(testConfig as FlowConfig));
            }).toPass({ timeout: 45_000 });
        });

        await test.step('Confirm the flow exists and is structured correctly.', async () => {
            await ShopAdmin.goesTo(AdminFlowBuilderListing.url());
            const flowListingRow = await AdminFlowBuilderListing.getLineItemByFlowName(testConfig.name);
            await ShopAdmin.expects(flowListingRow.flowActiveCheckmark).toBeVisible();
            await flowListingRow.flowContextMenuButton.click();
            await AdminFlowBuilderListing.contextMenuEdit.click();
            await ShopAdmin.expects(AdminFlowBuilderDetail.nameField).toHaveValue(testConfig.name);
            await ShopAdmin.expects(AdminFlowBuilderDetail.descriptionField).toHaveValue(testConfig.description);
            await ShopAdmin.expects(AdminFlowBuilderDetail.priorityField).toHaveValue(testConfig.priority);
            await AdminFlowBuilderDetail.flowTab.click();
            const trigger = await AdminFlowBuilderDetail.getTooltipText(AdminFlowBuilderDetail.triggerSelectField);
            await ShopAdmin.expects(trigger).toEqual(`${testConfig.triggerLabel}`);
            await ShopAdmin.expects(AdminFlowBuilderDetail.conditionRule).toHaveText(testConfig.condition);
            await ShopAdmin.expects(AdminFlowBuilderDetail.sequenceSeparator).toBeVisible();
            await ShopAdmin.expects(AdminFlowBuilderDetail.trueBlockActionDescription).toContainText(
                testConfig.trueActionIdentifier,
            );
            await ShopAdmin.expects(AdminFlowBuilderDetail.falseBlockActionDescription).toContainText(
                testConfig.falseActionIdentifier,
            );
        });
    },
);
