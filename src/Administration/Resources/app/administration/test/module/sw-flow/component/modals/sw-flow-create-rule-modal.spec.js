import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-flow/component/modals/sw-flow-create-rule-modal';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';

function createRuleMock(isNew) {
    return {
        id: '1',
        name: 'Test rule',
        isNew: () => isNew,
        conditions: {
            entity: 'rule',
            source: 'foo/rule'
        }
    };
}

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-flow-create-rule-modal'), {
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        create: () => {
                            return createRuleMock(true);
                        },
                        get: () => Promise.resolve(createRuleMock(false)),
                        save: () => Promise.resolve()
                    };
                }
            },
            ruleConditionDataProviderService: {
                getModuleTypes: () => []
            }
        },

        propsData: {
            sequence: {}
        },

        stubs: {
            'sw-tabs': Shopware.Component.build('sw-tabs'),
            'sw-tabs-item': Shopware.Component.build('sw-tabs-item'),
            'sw-modal': {
                template: `
                    <div class="sw-modal">
                      <slot name="modal-header"></slot>
                      <slot></slot>
                      <slot name="modal-footer"></slot>
                    </div>
                `
            },
            'sw-button': {
                template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>'
            },
            'sw-icon': true,
            'sw-condition-tree': true,
            'sw-container': true,
            'sw-multi-select': true,
            'sw-textarea-field': true,
            'sw-number-field': true,
            'sw-text-field': true
        }
    });
}

describe('module/sw-flow/component/sw-flow-create-rule-modal', () => {
    it('should show element correctly', async () => {
        const wrapper = createWrapper();

        const conditionElement = wrapper.find('.sw-flow-create-rule-modal__rule');
        expect(conditionElement.exists()).toBe(true);

        const fieldClasses = [
            '.sw-flow-create-rule-modal__name',
            '.sw-flow-create-rule-modal__priority',
            '.sw-flow-create-rule-modal__description',
            '.sw-flow-create-rule-modal__type'
        ];

        const detailHeaderTab = wrapper.find('.sw-flow-create-rule-modal__tab-detail');
        detailHeaderTab.trigger('click');
        await wrapper.vm.$nextTick();

        fieldClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).exists()).toBe(true);
        });
    });

    it('should emit event process-finish when saving rule sucessfully', async () => {
        const wrapper = await createWrapper();

        const saveButton = wrapper.find('.sw-flow-create-rule-modal__save-button');
        await saveButton.trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted()['process-finish']).toBeTruthy();
    });
});
