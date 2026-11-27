import { test, setViewport, replaceElements, hideElements, assertScreenshot } from '@fixtures/AcceptanceTest';

test('Visual: Rule Builder Detail page', { tag: '@Visual' }, async ({ ShopAdmin, AdminRuleDetail, TestDataService }) => {
    const rule = await TestDataService.createBasicRule({});
    await test.step('Creates a screenshot of the Rule Builder general tab.', async () => {
        await ShopAdmin.goesTo(AdminRuleDetail.url(rule.id));
        await setViewport(AdminRuleDetail.page, {
            waitForSelector: AdminRuleDetail.conditionORContainer,
        });
        await replaceElements(AdminRuleDetail.page, [
            AdminRuleDetail.header,
            AdminRuleDetail.nameInput,
        ]);
        await assertScreenshot(AdminRuleDetail.page, 'Rule-Builder-General.png');
    });
    await test.step('Creates a screenshot of the Rule Builder assignments tab.', async () => {
        await ShopAdmin.goesTo(AdminRuleDetail.url(rule.id, 'assignments'));
        await ShopAdmin.expects(async () => {
            const assignmentCards = AdminRuleDetail.page.locator('.mt-empty-state');
            const assignmentCardCount = await assignmentCards.count();

            await ShopAdmin.expects(assignmentCardCount).toBeGreaterThanOrEqual(11);
        }).toPass({
            intervals: [
                1_000,
                2_500,
            ],
        });
        // The assignment searches have already finished once the empty states are rendered, so waiting for one of their responses here would never resolve.
        await setViewport(AdminRuleDetail.page, {
            waitForSelector: AdminRuleDetail.shippingMethodAvailabilityRulesCard,
        });
        await replaceElements(AdminRuleDetail.page, [AdminRuleDetail.header]);
        await assertScreenshot(AdminRuleDetail.page, 'Rule-Builder-Detail-Assignments.png');
    });
    await test.step('Creates a screenshot of the Rule Builder add assignments modal.', async () => {
        const shippingMethodsLoaded = AdminRuleDetail.page.waitForResponse((response) =>
            response.url().includes('api/search/shipping-method'),
        );
        await AdminRuleDetail.shippingMethodAvailabilityRulesCard.getByText('Add assignment').click();
        await shippingMethodsLoaded;
        await setViewport(AdminRuleDetail.page, {
            waitForSelector: AdminRuleDetail.assignmentModal,
            width: 800,
            contentHeight: 600,
        });
        await replaceElements(AdminRuleDetail.page, [AdminRuleDetail.header]);
        await hideElements(AdminRuleDetail.page, [AdminRuleDetail.adminMenuAvatar]);
        await assertScreenshot(
            AdminRuleDetail.page,
            'Rule-Builder-Detail-Assignments-Modal.png',
            AdminRuleDetail.assignmentModal,
        );
    });
});
