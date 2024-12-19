import { test } from '@fixtures/AcceptanceTest';

test('As an admin user, I want to have an overview of my assigned rules, so that I can easily see where they are used and easily assign new ones', { tag: '@Rule' }, async ({
    TestDataService,
    ShopAdmin,
    AdminRuleDetail,
    AdminShippingDetail,
}) => {

    const rule = await TestDataService.createBasicRule();
    const shippingMethod = await TestDataService.createBasicShippingMethod({availabilityRuleId: rule.id});

    await ShopAdmin.goesTo(AdminRuleDetail.url(rule.id, 'assignments'));
    await ShopAdmin.expects(AdminRuleDetail.shippingMethodAvailabilityRulesCardTable).toContainText(shippingMethod.name);
    await ShopAdmin.expects(AdminRuleDetail.paymentMethodsAvailabilityRulesCardEmptyState).toHaveText('This rule is not in use');
    await ShopAdmin.expects(AdminRuleDetail.taxProviderRulesCardEmptyState).toHaveText('This rule is not in use');
    await ShopAdmin.expects(AdminRuleDetail.promotionOrderRulesCardEmptyState).toHaveText('This rule is not in use');
    await ShopAdmin.expects(AdminRuleDetail.promotionCustomerRulesCardEmptyState).toHaveText('This rule is not in use');
    await ShopAdmin.expects(AdminRuleDetail.promotionCartRulesCardEmptyState).toHaveText('This rule is not in use');
    await AdminRuleDetail.shippingMethodAvailabilityRulesCardLink.getByText(shippingMethod.name).click();
    await ShopAdmin.expects(AdminShippingDetail.header).toHaveText(shippingMethod.name);
    await ShopAdmin.expects(AdminShippingDetail.nameField).toHaveValue(shippingMethod.name);
    await ShopAdmin.expects(AdminShippingDetail.availabilityRuleField).toHaveText(rule.name);

    // remove if flaky
    await AdminShippingDetail.availabilityRuleField.click();
    await ShopAdmin.expects(AdminShippingDetail.getRuleSelectionCheckmark(rule.name)).toBeVisible();
})
