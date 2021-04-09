import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-settings-rule/view/sw-settings-rule-detail-base';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-rule-detail-base'), {
        localVue,
        stubs: {
            'sw-card': true,
            'sw-loader': true,
            'sw-condition-tree': true,
            'sw-container': true,
            'sw-field': true,
            'sw-multi-select': true
        },
        propsData: {
            conditionRepository: {},
            ruleId: 'uuid1',
            rule: {
                name: 'Test rule',
                priority: 7,
                description: 'Foo, bar',
                type: ''
            },
            isLoading: false
        },
        provide: {
            ruleConditionDataProviderService: {
                getModuleTypes: () => []
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            customFieldDataProviderService: {
                getCustomFieldSets: () => Promise.resolve([])
            }
        }
    });
}

describe('src/module/sw-settings-rule/view/sw-settings-rule-detail-base', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have disabled fields', async () => {
        const wrapper = createWrapper();

        const ruleNameField = wrapper.find('sw-field-stub[label="sw-settings-rule.detail.labelName"]');
        const rulePriorityField = wrapper.find('sw-field-stub[label="sw-settings-rule.detail.labelPriority"]');
        const ruleDescriptionField = wrapper.find('sw-field-stub[label="sw-settings-rule.detail.labelDescription"]');
        const moduleTypesField = wrapper.find('sw-multi-select-stub[label="sw-settings-rule.detail.labelType"]');
        const conditionTree = wrapper.find('sw-condition-tree-stub');

        expect(ruleNameField.attributes().disabled).toBe('true');
        expect(rulePriorityField.attributes().disabled).toBe('true');
        expect(ruleDescriptionField.attributes().disabled).toBe('true');
        expect(moduleTypesField.attributes().disabled).toBe('true');
        expect(conditionTree.attributes().disabled).toBe('true');
    });

    it('should have enabled fields', async () => {
        const wrapper = createWrapper([
            'rule.editor'
        ]);

        const ruleNameField = wrapper.find('sw-field-stub[label="sw-settings-rule.detail.labelName"]');
        const rulePriorityField = wrapper.find('sw-field-stub[label="sw-settings-rule.detail.labelPriority"]');
        const ruleDescriptionField = wrapper.find('sw-field-stub[label="sw-settings-rule.detail.labelDescription"]');
        const moduleTypesField = wrapper.find('sw-multi-select-stub[label="sw-settings-rule.detail.labelType"]');
        const conditionTree = wrapper.find('sw-condition-tree-stub');

        expect(ruleNameField.attributes().disabled).toBeUndefined();
        expect(rulePriorityField.attributes().disabled).toBeUndefined();
        expect(ruleDescriptionField.attributes().disabled).toBeUndefined();
        expect(moduleTypesField.attributes().disabled).toBeUndefined();
        expect(conditionTree.attributes().disabled).toBeUndefined();
    });
});
