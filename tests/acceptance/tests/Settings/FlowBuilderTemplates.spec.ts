import { test } from '@fixtures/AcceptanceTest';
import { getFlowId, compareFlowTemplateWithFlow } from '@shopware-ag/acceptance-test-suite';

test(
    'As an admin, I want to create new flows from templates, so that I can easily create new ones based on the default flows.',
    { tag: '@Flow' },
    async ({
        ShopAdmin,
        AdminFlowBuilderTemplates,
        AdminFlowBuilderCreate,
        AdminFlowBuilderDetail,
        IdProvider,
        AdminApiContext,
    }) => {
        const flowTemplateName = 'Order placed';
        const flowTemplateSingleTerms = flowTemplateName.split(' ');
        const flowTemplateSearchTerm = flowTemplateSingleTerms[flowTemplateSingleTerms.length - 2];
        const uniqueId = IdProvider.getIdPair().uuid;
        const flowName = 'Test flow - ' + uniqueId;

        const getFlowTemplateRow = async () => {
            const searchResponse = AdminFlowBuilderTemplates.page.waitForResponse((response) => {
                if (!response.url().includes('/api/search/flow-template') || response.request().method() !== 'POST') {
                    return false;
                }

                const requestData = response.request().postDataJSON() as { term?: string } | null;

                return requestData?.term === flowTemplateSearchTerm;
            });

            await AdminFlowBuilderTemplates.searchBar.fill(flowTemplateSearchTerm);
            await searchResponse;

            const row = await AdminFlowBuilderTemplates.getLineItemByFlowName(flowTemplateName);
            await ShopAdmin.expects(row.lineItem).toBeVisible();

            return row;
        };

        await test.step('Go to flow template detail page and retrieve template UUID', async () => {
            await ShopAdmin.goesTo(AdminFlowBuilderTemplates.url());
            await ShopAdmin.expects(AdminFlowBuilderTemplates.searchBar).toBeVisible();
            const adminFlowBuilderTemplatesRow = await getFlowTemplateRow();
            await adminFlowBuilderTemplatesRow.lineItem
                .locator('.sw-flow-list-my-flows__content__update-flow-template-link')
                .click();
            await ShopAdmin.expects(AdminFlowBuilderDetail.generalTab).toBeVisible();
            await ShopAdmin.expects(AdminFlowBuilderDetail.templateName).toHaveValue(flowTemplateName);
            await ShopAdmin.expects(AdminFlowBuilderDetail.alertWarning).toContainText('Flow templates cannot be edited.');
        });

        await test.step('Create flow from template and compare it with template', async () => {
            const flowTemplateUrl = AdminFlowBuilderDetail.page.url().split('/');
            const flowTemplateId = flowTemplateUrl[flowTemplateUrl.length - 2];
            await ShopAdmin.goesTo(AdminFlowBuilderTemplates.url());
            await ShopAdmin.expects(AdminFlowBuilderTemplates.searchBar).toBeVisible();
            const adminFlowBuilderTemplatesRow = await getFlowTemplateRow();
            await adminFlowBuilderTemplatesRow.lineItem.locator('.sw-flow-list-my-flows__content__create-flow-link').click();
            await ShopAdmin.expects(AdminFlowBuilderCreate.smartBarHeader).toContainText(flowTemplateName);
            await AdminFlowBuilderCreate.nameField.fill(flowName);
            await AdminFlowBuilderCreate.saveButton.click();
            await ShopAdmin.expects(AdminFlowBuilderDetail.saveButtonLoader).toBeVisible();
            await ShopAdmin.expects(AdminFlowBuilderDetail.saveButtonLoader).not.toBeVisible();
            const flowId = await getFlowId(flowName, AdminApiContext);
            const flowEqualsTemplate = await compareFlowTemplateWithFlow(flowId, flowTemplateId, AdminApiContext);
            ShopAdmin.expects(flowEqualsTemplate).toBe(true);
        });
    },
);
