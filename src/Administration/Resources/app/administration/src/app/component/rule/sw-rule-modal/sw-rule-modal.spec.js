/**
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

function createRuleMock(isNew) {
    return {
        id: '1',
        name: 'Test rule',
        isNew: () => isNew,
        conditions: [{
            entity: 'rule',
            source: 'foo/rule',
            children: [{
                id: 'some-id',
                children: [{
                    id: 'some-id',
                }],
            }],
        }],
        someRuleRelation: [],
    };
}

async function createWrapper() {
    return mount(await wrapTestComponent('sw-rule-modal', { sync: true }), {
        props: {
            sequence: {},
            ruleAwareGroupKey: 'someRuleRelation',
        },
        global: {
            renderStubDefaultSlot: true,
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
                    addScriptConditions: () => {
                    },
                    getRestrictedRuleTooltipConfig: () => ({
                        disabled: true,
                    }),
                },

                ruleConditionsConfigApiService: {
                    load: () => Promise.resolve(),
                },
            },
            stubs: {
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
                'sw-field': true,
            },
        },
    });
}

describe('app/component/rule/sw-rule-modal', () => {
    it('should emit event save when saving rule successfully', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.saveAndClose();
        await flushPromises();

        expect(wrapper.emitted().save).toBeTruthy();
    });

    it('should create notification and prevent saving', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.ruleConditionDataProviderService.getRestrictedRuleTooltipConfig = () => {
            return { disabled: false, message: 'Awareness error' };
        };
        await flushPromises();

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.find('.sw-rule-modal__save').trigger('click');
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'Awareness error',
            title: 'global.default.error',
        });

        expect(wrapper.emitted().save).toBeFalsy();
    });
});
