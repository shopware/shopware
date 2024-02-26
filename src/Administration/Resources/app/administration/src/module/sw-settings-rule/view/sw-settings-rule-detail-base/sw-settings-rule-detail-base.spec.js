import { reactive } from 'vue';
import { mount } from '@vue/test-utils';
import RuleConditionService from 'src/app/service/rule-condition.service';

/**
 * @package services-settings
 */

const swConditionTree = {
    props: ['initial-conditions'],
    template: '<div class="sw-condition-tree"></div>',
};

async function createWrapper(privileges = []) {
    // localVue.directive('tooltip', {});

    return mount(await wrapTestComponent('sw-settings-rule-detail-base', { sync: true }), {
        global: {
            stubs: {
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-loader': true,
                'sw-condition-tree': swConditionTree,
                'sw-container': true,
                'sw-textarea-field': true,
                'sw-number-field': true,
                'sw-text-field': true,
                'sw-multi-select': true,
                'sw-entity-tag-select': true,
                'sw-custom-field-set-renderer': true,
            },
            provide: {
                ruleConditionDataProviderService: new RuleConditionService(),
                acl: {
                    can: (identifier) => {
                        return privileges.includes(identifier);
                    },
                },
                customFieldDataProviderService: {
                    getCustomFieldSets: () => Promise.resolve([
                        reactive({
                            id: '018a848f9592774c8e8b4c9eb21370b7',
                            name: 'custom_rule_set',
                            active: true,
                            global: 'false',
                            customFields: [
                                {
                                    id: '018a8490c9df7c8bbc4fd331739f1d0a',
                                    name: 'custom_rule_set_field',
                                    active: true,
                                },
                            ],
                        }),
                    ]),
                },
            },
            directive: {
                tooltip: {},
            },
        },
        props: {
            conditionRepository: {},
            ruleId: 'uuid1',
            rule: {
                name: 'Test rule',
                id: 'rule-id',
                priority: 7,
                description: 'Foo, bar',
                moduleTypes: {
                    types: [],
                },
            },
            isLoading: false,
        },
    });
}

describe('src/module/sw-settings-rule/view/sw-settings-rule-detail-base', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    describe('sw-settings-rule-detail-base-content', () => {
        it('should have disabled fields', async () => {
            const wrapper = await createWrapper();

            const ruleNameField = wrapper.get('sw-text-field-stub[label="sw-settings-rule.detail.labelName"]');
            const rulePriorityField = wrapper.get('sw-number-field-stub[label="sw-settings-rule.detail.labelPriority"]');
            const ruleDescriptionField = wrapper.get('sw-textarea-field-stub[label="sw-settings-rule.detail.labelDescription"]');
            const moduleTypesField = wrapper.get('sw-multi-select-stub[label="sw-settings-rule.detail.labelType"]');

            [
                ruleNameField,
                rulePriorityField,
                ruleDescriptionField,
                moduleTypesField,
            ].forEach((element) => {
                expect(element.attributes().disabled).toBe('true');
            });
        });

        it('should have enabled fields', async () => {
            const wrapper = await createWrapper([
                'rule.editor',
            ]);

            const ruleNameField = wrapper.get('sw-text-field-stub[label="sw-settings-rule.detail.labelName"]');
            const rulePriorityField = wrapper.get('sw-number-field-stub[label="sw-settings-rule.detail.labelPriority"]');
            const ruleDescriptionField = wrapper.get('sw-textarea-field-stub[label="sw-settings-rule.detail.labelDescription"]');
            const moduleTypesField = wrapper.get('sw-multi-select-stub[label="sw-settings-rule.detail.labelType"]');
            [
                ruleNameField,
                rulePriorityField,
                ruleDescriptionField,
                moduleTypesField,
            ].forEach((element) => {
                expect(element.attributes().disabled).toBeUndefined();
            });
        });
    });

    describe('sw-settings-rule-detail__condition_container', () => {
        it('renders condition tree', async () => {
            const wrapper = await createWrapper([
                'rule.editor',
            ]);

            const conditionTree = wrapper.get('.sw-condition-tree');

            expect(conditionTree.exists()).toBe(true);
        });

        it('emits changed conditions from sub component', async () => {
            const wrapper = await createWrapper([
                'rule.editor',
            ]);

            const conditionTree = wrapper.getComponent(swConditionTree);

            await conditionTree.vm.$emit('conditions-changed', [{
                id: 'some-condition-id',
                ruleId: 'rule-id',
            }]);

            expect(wrapper.emitted('conditions-changed')).toBeTruthy();
            expect(wrapper.emitted('conditions-changed')).toHaveLength(1);
            expect(wrapper.emitted('conditions-changed')[0]).toEqual([[{
                id: 'some-condition-id',
                ruleId: 'rule-id',
            }]]);
        });

        it('emits initial loading', async () => {
            const wrapper = await createWrapper([
                'rule.editor',
            ]);

            const conditionTree = wrapper.getComponent(swConditionTree);

            await conditionTree.vm.$emit('initial-loading-done');

            expect(wrapper.emitted('tree-finished-loading')).toBeTruthy();
        });
    });

    describe('sw-settings-rule-detail-base-custom-field-sets', () => {
        it('should render custom fields', async () => {
            const wrapper = await createWrapper([
                'rule.editor',
            ]);

            const customFieldSetRenderer = wrapper.get('sw-custom-field-set-renderer-stub');

            expect(customFieldSetRenderer.exists()).toBe(true);
        });
    });
});
