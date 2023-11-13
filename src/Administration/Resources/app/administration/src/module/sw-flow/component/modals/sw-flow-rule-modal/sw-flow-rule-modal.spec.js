import { shallowMount } from '@vue/test-utils';
import swFlowRuleModal from 'src/module/sw-flow/component/modals/sw-flow-rule-modal';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';
import flowState from 'src/module/sw-flow/state/flow.state';

Shopware.Component.register('sw-flow-rule-modal', swFlowRuleModal);

function createRuleMock(isNew) {
    return {
        id: '1',
        name: 'Test rule',
        isNew: () => isNew,
        conditions: {
            entity: 'rule',
            source: 'foo/rule',
        },
    };
}

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-flow-rule-modal'), {
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        create: () => {
                            return createRuleMock(true);
                        },
                        get: () => Promise.resolve(createRuleMock(false)),
                        save: () => Promise.resolve(),
                        search: () => Promise.resolve([]),
                    };
                },
            },

            ruleConditionDataProviderService: {
                getModuleTypes: () => [],
                addScriptConditions: () => {},
                getAwarenessConfigurationByAssignmentName: () => ({}),
            },

            ruleConditionsConfigApiService: {
                load: () => Promise.resolve(),
            },
        },

        propsData: {
            sequence: {},
        },

        stubs: {
            'sw-tabs': await Shopware.Component.build('sw-tabs'),
            'sw-tabs-item': await Shopware.Component.build('sw-tabs-item'),
            'sw-modal': {
                template: `
                    <div class="sw-modal">
                      <slot name="modal-header"></slot>
                      <slot></slot>
                      <slot name="modal-footer"></slot>
                    </div>
                `,
            },
            'sw-button': {
                template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>',
            },
            'sw-button-process': {
                template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>',
            },
            'sw-icon': true,
            'sw-condition-tree': true,
            'sw-container': true,
            'sw-multi-select': true,
            'sw-textarea-field': true,
            'sw-number-field': true,
            'sw-text-field': true,
        },
    });
}

describe('module/sw-flow/component/sw-flow-rule-modal', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', flowState);
    });

    it('should show element correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const conditionElement = wrapper.find('.sw-flow-rule-modal__tab-rule');
        expect(conditionElement.exists()).toBe(true);

        const fieldClasses = [
            '.sw-flow-rule-modal__name',
            '.sw-flow-rule-modal__priority',
            '.sw-flow-rule-modal__description',
            '.sw-flow-rule-modal__type',
        ];

        const detailHeaderTab = wrapper.find('.sw-flow-rule-modal__tab-detail');
        await detailHeaderTab.trigger('click');
        await flushPromises();

        fieldClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).exists()).toBe(true);
        });
    });

    it('should emit event process-finish when saving rule successfully', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const saveButton = wrapper.find('.sw-flow-rule-modal__save-button');
        await saveButton.trigger('click');
        await flushPromises();

        expect(wrapper.emitted()['process-finish']).toBeTruthy();
    });
});
