import { test } from '@fixtures/AcceptanceTest';
import { RuleType } from '@shopware-ag/acceptance-test-suite';
test('As an admin user, I want to filter and add rule assignments, to easily add new entities to a rule', { tag: '@Rule' }, async ({
    ShopAdmin,
    TestDataService,
    AdminRuleDetail,
    AssignEntitiesToRule,
}) => {
    const rule = await TestDataService.createBasicRule();
    const shippingMethod1 = await TestDataService.createBasicShippingMethod();
    const shippingMethod2 = await TestDataService.createBasicShippingMethod();
    const entities = [
        { entity: shippingMethod1, ruleType: RuleType.shippingAvailability },
        { entity: shippingMethod2, ruleType: RuleType.shippingAvailability },
    ];
    await ShopAdmin.goesTo(AdminRuleDetail.url(rule.id, 'assignments'));
    await ShopAdmin.attemptsTo(AssignEntitiesToRule(entities));
    await ShopAdmin.expects(AdminRuleDetail.shippingMethodAvailabilityRulesCard).toContainText(shippingMethod1.name, shippingMethod2.name);
    await AdminRuleDetail.shippingMethodAvailabilityRulesCardSearchField.fill(shippingMethod1.name);
    await ShopAdmin.expects(AdminRuleDetail.shippingMethodAvailabilityRulesCardTable).toContainText(shippingMethod1.name);
    await ShopAdmin.expects(AdminRuleDetail.shippingMethodAvailabilityRulesCardTable).not.toContainText(shippingMethod2.name);
});
