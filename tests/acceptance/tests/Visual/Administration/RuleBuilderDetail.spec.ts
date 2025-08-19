import { test, expect } from '@fixtures/AcceptanceTest';
import { setViewport, replaceElements, hideElements } from '@shopware-ag/acceptance-test-suite';

test('Visual: Rule Builder Detail page', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminRuleDetail,
    TestDataService,
}) => {

    const rule = await TestDataService.createBasicRule({})
    await test.step('Creates a screenshot of the Rule Builder general tab.', async () => {
        await ShopAdmin.goesTo(AdminRuleDetail.url(rule.id));
        await setViewport(AdminRuleDetail.page, {
            waitForSelector: '.sw-condition-or-container',
        });
        await replaceElements(AdminRuleDetail.page, [
            AdminRuleDetail.header,
        ]);
        await hideElements(AdminRuleDetail.page, [
            AdminRuleDetail.nameInput,
        ]);
        await expect(AdminRuleDetail.contentView).toHaveScreenshot('Rule-Builder-General.png');
    });
    await test.step('Creates a screenshot of the Rule Builder assignments tab.', async () => {
        await ShopAdmin.goesTo(AdminRuleDetail.url(rule.id, 'assignments'));
        await setViewport(AdminRuleDetail.page, {
            requestURL: 'api/search/shipping-method',
        });
        await replaceElements(AdminRuleDetail.page, [
            AdminRuleDetail.header,
        ]);

        await expect(AdminRuleDetail.contentView).toHaveScreenshot('Rule-Builder-Detail-Assignments.png');
    });
    await test.step('Creates a screenshot of the Rule Builder add assignments modal.', async () => {
        await ShopAdmin.goesTo(AdminRuleDetail.url(rule.id, 'assignments'));
        await AdminRuleDetail.shippingMethodAvailabilityRulesCard.getByText('Add assignment').click();
        await setViewport(AdminRuleDetail.page, {
            requestURL: 'api/search/shipping-method',
            width: 800,
            contentHeight: 600,
        });
        await replaceElements(AdminRuleDetail.page, [
            AdminRuleDetail.header,
        ]);
        await expect(AdminRuleDetail.assignmentModal).toHaveScreenshot('Rule-Builder-Detail-Assignments-Modal.png');
    });
});
