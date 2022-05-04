import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-promotion-v2/component/sw-promotion-v2-rule-select';

function createWrapper(customProps = {}, customOptions = {}) {
    return shallowMount(Shopware.Component.build('sw-promotion-v2-rule-select'), {
        stubs: {
            'sw-entity-many-to-many-select': true,
            'sw-arrow-field': true,
            'sw-grouped-single-select': true
        },
        provide: {
            ruleConditionDataProviderService: {},
        },
        propsData: {
            ...customProps
        },
        ...customOptions
    });
}

describe('src/module/sw-promotion-v2/component/sw-promotion-v2-rule-select', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have disabled tooltip without rule aware group key property', async () => {
        const wrapper = createWrapper({}, {
            provide: {
                ruleConditionDataProviderService: {},
            },
        });

        const tooltipConfig = wrapper.vm.tooltipConfig({
            conditions: [],
        });

        expect(tooltipConfig.disabled).toBeTruthy();
    });

    it.only('should have disabled tooltip', async () => {
        const wrapper = createWrapper({
            ruleAwareGroupKey: 'assignmentOne'
        }, {
            provide: {
                ruleConditionDataProviderService: {
                    getRestrictionsByAssociation: () => ({
                        assignmentName: 'assignmentOne',
                        isRestricted: false,
                    })
                },
            },
        });

        const tooltipConfig = wrapper.vm.tooltipConfig({
            conditions: [],
        });

        expect(tooltipConfig.disabled).toBeTruthy();
    });

    it('should have enabled tooltip', async () => {
        const wrapper = createWrapper({
            ruleAwareGroupKey: 'assignmentOne'
        }, {
            provide: {
                ruleConditionDataProviderService: {
                    getRestrictionsByAssociation: () => ({
                        assignmentName: 'assignmentOne',
                        notEqualsViolations: [],
                        equalsAnyMatched: [{ type: 'conditionType2' }, { type: 'conditionType3' }],
                        equalsAnyNotMatched: [],
                        isRestricted: true,
                        assignmentSnippet: 'sw-assignment-one-snippet'
                    })
                },
            },
        });

        const tooltipConfig = wrapper.vm.tooltipConfig({
            conditions: [],
        });

        expect(tooltipConfig.disabled).toBeFalsy();
    });

    it('should return true when rule is restricted', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_18215'];
        const wrapper = createWrapper({
            ruleAwareGroupKey: 'assignmentOne'
        }, {
            provide: {
                ruleConditionDataProviderService: {
                    getRestrictionsByAssociation: () => ({
                        assignmentName: 'assignmentOne',
                        notEqualsViolations: [],
                        equalsAnyMatched: [{ type: 'conditionType2' }, { type: 'conditionType3' }],
                        equalsAnyNotMatched: [],
                        isRestricted: true,
                        assignmentSnippet: 'sw-assignment-one-snippet'
                    })
                },
            },
        });

        expect(wrapper.vm.isRuleRestricted({ conditions: [] })).toBeTruthy();
    });

    it('should return restricted false when rule aware group key property is empty ', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_18215'];
        const wrapper = createWrapper({}, {});
        expect(wrapper.vm.isRuleRestricted({ conditions: [] })).toBeFalsy();
    });
});
