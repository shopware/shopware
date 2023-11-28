import { createLocalVue, shallowMount } from '@vue/test-utils';
import swFlowChangeCustomerStatusModal from 'src/module/sw-flow/component/modals/sw-flow-change-customer-status-modal';

import Vuex from 'vuex';

Shopware.Component.register('sw-flow-change-customer-status-modal', swFlowChangeCustomerStatusModal);

async function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(await Shopware.Component.build('sw-flow-change-customer-status-modal'), {
        localVue,

        propsData: {
            sequence: {},
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
            'sw-single-select': {
                model: {
                    prop: 'value',
                    event: 'change',
                },
                props: ['value'],
                template: `
                    <div class="sw-single-select">
                        <input
                            class="sw-single-select__selection-input"
                            :value="value"
                            @input="$emit('change', $event.target.value)"
                        />
                        <slot></slot>
                    </div>
                `,
            },
        },
    });
}

describe('module/sw-flow/component/sw-flow-change-customer-status-modal', () => {
    it('should emit process-finish when customer status is selected', async () => {
        const wrapper = await createWrapper();

        const customerStatusInput = wrapper.find('.sw-single-select__selection-input');
        await customerStatusInput.setValue(false);
        await customerStatusInput.trigger('input');

        const saveButton = wrapper.find('.sw-flow-change-customer-status-modal__save-button');
        await saveButton.trigger('click');

        expect(wrapper.emitted()['process-finish'][0]).toEqual([{
            config: {
                active: 'false',
            },
        }]);
    });
});
