import { reactive } from 'vue';
import { mount } from '@vue/test-utils';
import RuleConditionService from 'src/app/service/rule-condition.service';

/**
 * @package services-settings
 * @group disabledCompat
 */

const swConditionTree = {
    props: ['initial-conditions'],
    template: '<div class="sw-condition-tree"></div>',
};

const defaultProps = {
    conditionRepository: {},
    isLoading: false,
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
};

async function createWrapper(props = defaultProps, privileges = ['rule.editor']) {
    return mount(await wrapTestComponent('sw-settings-rule-detail-base', { sync: true }), {
        props,
        global: {
            stubs: {
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                'sw-multi-select': await wrapTestComponent('sw-multi-select'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                'sw-condition-tree': swConditionTree,
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': {
                    template: '<div class="sw-popover"><slot></slot></div>',
                },
                'sw-text-field': true,
                'sw-number-field': true,
                'sw-textarea-field': true,
                'sw-entity-tag-select': true,
                'sw-loader': true,
                'sw-custom-field-set-renderer': true,
                'sw-extension-component-section': true,
                'sw-ai-copilot-badge': true,
                'sw-context-button': true,
                'sw-highlight-text': true,
                'sw-icon': true,
                'sw-inheritance-switch': true,
                'sw-help-text': true,
                'sw-field-error': true,
                'sw-label': true,
                'sw-button': true,
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
        },
    });
}

describe('src/module/sw-settings-rule/view/sw-settings-rule-detail-base', () => {
    describe('sw-settings-rule-detail-base-content', () => {
        it('should have disabled fields', async () => {
            const wrapper = await createWrapper(defaultProps, []);
            await flushPromises();

            const ruleNameField = wrapper.find('sw-text-field-stub[name=sw-field--rule-name]');
            const rulePriorityField = wrapper.find('sw-number-field-stub[name=sw-field--rule-priority]');
            const ruleDescriptionField = wrapper.find('sw-textarea-field-stub[name=sw-field--rule-description]');

            expect(ruleNameField.attributes().disabled).toBe('true');
            expect(rulePriorityField.attributes().disabled).toBe('true');
            expect(ruleDescriptionField.attributes().disabled).toBe('true');

            expect(wrapper.find('.sw-settings-rule-detail__type-field').classes()).toContain('is--disabled');
        });

        it('should have enabled fields', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const ruleNameField = wrapper.find('sw-text-field-stub[name=sw-field--rule-name]');
            const rulePriorityField = wrapper.find('sw-number-field-stub[name=sw-field--rule-priority]');
            const ruleDescriptionField = wrapper.find('sw-textarea-field-stub[name=sw-field--rule-description]');

            expect(ruleNameField.attributes().disabled).toBeUndefined();
            expect(rulePriorityField.attributes().disabled).toBeUndefined();
            expect(ruleDescriptionField.attributes().disabled).toBeUndefined();

            expect(wrapper.find('.sw-settings-rule-detail__type-field').classes()).not.toContain('is--disabled');
        });

        it('should set module types', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.find('.sw-settings-rule-detail__type-field').exists()).toBe(true);
            await wrapper.find('.sw-select__selection-indicators').trigger('click');
            await flushPromises();

            await wrapper.find('.sw-select-result').trigger('click');
            await flushPromises();

            expect(wrapper.vm.rule.moduleTypes).toEqual({ types: ['shipping'] });
        });

        it('should set module types to null if value is empty', async () => {
            const wrapper = await createWrapper({
                ...defaultProps,
                rule: {
                    ...defaultProps.rule,
                    moduleTypes: {
                        types: ['shipping'],
                    },
                },
            });
            await flushPromises();

            expect(wrapper.find('.sw-settings-rule-detail__type-field').exists()).toBe(true);
            await wrapper.find('.sw-select__selection-indicators').trigger('click');
            await flushPromises();

            await wrapper.find('.sw-select-result').trigger('click');
            await flushPromises();

            expect(wrapper.vm.rule.moduleTypes).toBeNull();
        });
    });

    describe('sw-settings-rule-detail__condition_container', () => {
        it('renders condition tree', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const conditionTree = wrapper.get('.sw-condition-tree');

            expect(conditionTree.exists()).toBe(true);
        });

        it('emits changed conditions from sub component', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

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
            const wrapper = await createWrapper();
            await flushPromises();

            const conditionTree = wrapper.getComponent(swConditionTree);

            await conditionTree.vm.$emit('initial-loading-done');

            expect(wrapper.emitted('tree-finished-loading')).toBeTruthy();
        });
    });

    describe('sw-settings-rule-detail-base-custom-field-sets', () => {
        it('should render custom fields', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const customFieldSetRenderer = wrapper.get('sw-custom-field-set-renderer-stub');

            expect(customFieldSetRenderer.exists()).toBe(true);
        });
    });
});
