import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-settings-rule/page/sw-settings-rule-detail';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-rule-detail'), {
        localVue,
        stubs: {
            'sw-page': {
                template: `
    <div>
        <slot name="smart-bar-actions"></slot>
        <slot name="content"></slot>
    </div>`
            },
            'sw-button': true,
            'sw-button-process': true,
            'sw-card': true,
            'sw-card-view': true,
            'sw-container': true,
            'sw-field': true,
            'sw-multi-select': true,
            'sw-condition-tree': true
        },
        provide: {
            ruleConditionDataProviderService: {
                getModuleTypes: () => []
            },
            repositoryFactory: {
                create: () => ({
                    create: () => ({
                        conditions: {
                            entity: 'rule',
                            source: 'foo/rule'
                        }
                    })
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }
        },
        mocks: {
            $tc: v => v,
            $device: {
                getSystemKey: () => {}
            }
        }
    });
}

describe('src/module/sw-settings-rule/page/sw-settings-rule-detail', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have disabled fields', async () => {
        const wrapper = createWrapper();

        const buttonSave = wrapper.find('.sw-settings-rule-detail__save-action');
        const ruleNameField = wrapper.find('sw-field-stub[label="sw-settings-rule.detail.labelName"]');
        const rulePriorityField = wrapper.find('sw-field-stub[label="sw-settings-rule.detail.labelPriority"]');
        const ruleDescriptionField = wrapper.find('sw-field-stub[label="sw-settings-rule.detail.labelDescription"]');
        const moduleTypesField = wrapper.find('sw-multi-select-stub[label="sw-settings-rule.detail.labelType"]');
        const conditionTree = wrapper.find('sw-condition-tree-stub');

        expect(buttonSave.attributes().disabled).toBe('true');
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

        const buttonSave = wrapper.find('.sw-settings-rule-detail__save-action');
        const ruleNameField = wrapper.find('sw-field-stub[label="sw-settings-rule.detail.labelName"]');
        const rulePriorityField = wrapper.find('sw-field-stub[label="sw-settings-rule.detail.labelPriority"]');
        const ruleDescriptionField = wrapper.find('sw-field-stub[label="sw-settings-rule.detail.labelDescription"]');
        const moduleTypesField = wrapper.find('sw-multi-select-stub[label="sw-settings-rule.detail.labelType"]');
        const conditionTree = wrapper.find('sw-condition-tree-stub');

        expect(buttonSave.attributes().disabled).toBeUndefined();
        expect(ruleNameField.attributes().disabled).toBeUndefined();
        expect(rulePriorityField.attributes().disabled).toBeUndefined();
        expect(ruleDescriptionField.attributes().disabled).toBeUndefined();
        expect(moduleTypesField.attributes().disabled).toBeUndefined();
        expect(conditionTree.attributes().disabled).toBeUndefined();
    });
});
