import { expect, test } from '@fixtures/AcceptanceTest';
import { getFlowId, compareFlowTemplateWithFlow } from "@shopware-ag/acceptance-test-suite";

test('As an admin, I want to create new flows from templates, so that I can easily create new ones based on the default flows.', { tag: '@Flow' }, async ({
        ShopAdmin,
        AdminFlowBuilderTemplates,
        AdminFlowBuilderCreate,
        AdminFlowBuilderDetail,
        IdProvider,
        AdminApiContext,

    }) => {

    // change flowTemplateName to test different flows/templates
    const flowTemplateName = 'Order placed';

    const flowTemplateSingleTerms = flowTemplateName.split(' ');
    const flowTemplateSearchTerm = flowTemplateSingleTerms[flowTemplateSingleTerms.length - 1];
    const uniqueId = IdProvider.getIdPair().uuid;
    const flowName = 'Test flow - ' + uniqueId;

    // go to flow template detail page and retrieve template's UUID
    // await ShopAdmin.goesTo(AdminFlowBuilderTemplates.url(`?limit=25&page=1&term=${flowTemplateSearchTerm}&sortBy=createdAt&sortDirection=DESC&naturalSorting=false`));
    // todo: the line above replaces the following two lines as soon as NEXT-40094 is resolved
    await ShopAdmin.goesTo(AdminFlowBuilderTemplates.url());
    // todo: wait for sth
    await AdminFlowBuilderTemplates.searchBar.fill(flowTemplateSearchTerm);
    const adminFlowBuilderTemplatesRow = await AdminFlowBuilderTemplates.getLineItemByFlowName(flowTemplateName);
    await adminFlowBuilderTemplatesRow.templateDetailLink.click();
    await ShopAdmin.expects(AdminFlowBuilderDetail.generalTab).toBeVisible();
    await ShopAdmin.expects(AdminFlowBuilderDetail.templateName).toHaveValue(flowTemplateName);
    await ShopAdmin.expects(AdminFlowBuilderDetail.alertWarning).toContainText('Flow templates cannot be edited.');
    const flowTemplateUrl = AdminFlowBuilderDetail.page.url().split('/');
    const flowTemplateId = flowTemplateUrl[flowTemplateUrl.length - 2];

    // create flow from template
    await ShopAdmin.goesTo(AdminFlowBuilderCreate.url(`${flowTemplateId}`));
    // todo: wait for sth
    await ShopAdmin.expects(AdminFlowBuilderCreate.smartBarHeader).toContainText(flowTemplateName);
    await AdminFlowBuilderCreate.nameField.fill(flowName);
    await AdminFlowBuilderCreate.saveButton.click();
    await ShopAdmin.expects(AdminFlowBuilderDetail.page.locator('.sw-alert__title')).toHaveText('Success');

    // compare flow template and flow
    const flowId = await getFlowId(flowName, AdminApiContext);
    const isEqual = await compareFlowTemplateWithFlow(flowId, flowTemplateId, AdminApiContext);
    expect(isEqual).toBe(true);
});
