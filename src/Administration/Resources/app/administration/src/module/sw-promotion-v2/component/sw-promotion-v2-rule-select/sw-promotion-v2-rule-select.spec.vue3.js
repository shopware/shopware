import { mount } from '@vue/test-utils_v3';

const ruleConditionDataProviderService = {
    getRestrictedRuleTooltipConfig: jest.fn(),
    isRuleRestricted: jest.fn(),
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-promotion-v2-rule-select', { sync: true }), {
        global: {
            stubs: {
                'sw-entity-many-to-many-select': true,
                'sw-arrow-field': true,
                'sw-grouped-single-select': true,
            },
            provide: {
                ruleConditionDataProviderService: ruleConditionDataProviderService,
            },
        },
    });
}

describe('src/module/sw-promotion-v2/component/sw-promotion-v2-rule-select', () => {
    it('should call the rule condition service with activated feature flag', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.tooltipConfig({});
        wrapper.vm.isRuleRestricted({});

        expect(ruleConditionDataProviderService.getRestrictedRuleTooltipConfig).toHaveBeenCalled();
        expect(ruleConditionDataProviderService.isRuleRestricted).toHaveBeenCalled();
    });
});
