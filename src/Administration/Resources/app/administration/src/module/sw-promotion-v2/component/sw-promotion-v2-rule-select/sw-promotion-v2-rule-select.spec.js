import { shallowMount } from '@vue/test-utils';
import swPromotionV2RuleSelect from 'src/module/sw-promotion-v2/component/sw-promotion-v2-rule-select';

Shopware.Component.register('sw-promotion-v2-rule-select', swPromotionV2RuleSelect);

const ruleConditionDataProviderService = {
    getRestrictedRuleTooltipConfig: jest.fn(),
    isRuleRestricted: jest.fn(),
};

async function createWrapper(customProps = {}, customOptions = {}) {
    return shallowMount(await Shopware.Component.build('sw-promotion-v2-rule-select'), {
        stubs: {
            'sw-entity-many-to-many-select': true,
            'sw-arrow-field': true,
            'sw-grouped-single-select': true,
        },
        provide: {
            ruleConditionDataProviderService: ruleConditionDataProviderService,
        },
        propsData: {
            ...customProps,
        },
        ...customOptions,
    });
}

describe('src/module/sw-promotion-v2/component/sw-promotion-v2-rule-select', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should call the rule condition service with activated feature flag', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.tooltipConfig({});
        wrapper.vm.isRuleRestricted({});

        expect(ruleConditionDataProviderService.getRestrictedRuleTooltipConfig).toHaveBeenCalled();
        expect(ruleConditionDataProviderService.isRuleRestricted).toHaveBeenCalled();
    });
});
