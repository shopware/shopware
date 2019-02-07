import { Application } from 'src/core/shopware';
import '../core/component/swag-condition-count-42';

Application.addServiceProviderDecorator('ruleConditionDataProviderService', (ruleConditionService) => {
    ruleConditionService.addCondition('swagCount42', {
        component: 'swag-condition-count-42',
        label: 'swag-custom-rule.condition.count-42'
    });

    return ruleConditionService;
});
