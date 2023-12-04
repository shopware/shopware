import { createLocalVue, shallowMount } from '@vue/test-utils_v2';
import swFlowChangeCustomerGroupModal from 'src/module/sw-flow/component/modals/sw-flow-change-customer-group-modal';

import Vuex from 'vuex_v2';
import flowState from 'src/module/sw-flow/state/flow.state';

Shopware.Component.register('sw-flow-change-customer-group-modal', swFlowChangeCustomerGroupModal);

const customerGroupMock = [
    {
        translated: { name: 'Test net group' },
        id: '1',
    },
    {
        translated: { name: 'Test gross group' },
        id: '2',
    },
    {
        translated: { name: 'Test VIP group' },
        id: '3',
    },
];

async function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(await Shopware.Component.build('sw-flow-change-customer-group-modal'), {
        localVue,
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        search: () => Promise.resolve(customerGroupMock),
                    };
                },
            },
        },

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
            'sw-entity-single-select': {
                model: {
                    prop: 'value',
                    event: 'change',
                },
                props: ['value'],
                template: `
                    <div class="sw-entity-single-select">
                        <input
                            class="sw-entity-single-select__selection-input"
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

describe('module/sw-flow/component/sw-flow-change-customer-group-modal', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', flowState);
    });

    it('should show validation if customer group field is empty', async () => {
        const wrapper = await createWrapper();

        const saveButton = wrapper.find('.sw-flow-change-customer-group-modal__save-button');
        await saveButton.trigger('click');

        const customerGroupSelect = wrapper.find('.sw-entity-single-select');
        expect(customerGroupSelect.attributes('error')).toBeTruthy();

        const customerGroupInput = wrapper.find('.sw-entity-single-select__selection-input');
        await customerGroupInput.setValue('1');
        await customerGroupInput.trigger('input');

        await saveButton.trigger('click');

        expect(customerGroupSelect.attributes('error')).toBeFalsy();
    });

    it('should emit process-finish when customer group is selected', async () => {
        const wrapper = await createWrapper();

        const customerGroupInput = wrapper.find('.sw-entity-single-select__selection-input');
        await customerGroupInput.setValue('2');
        await customerGroupInput.trigger('input');

        const saveButton = wrapper.find('.sw-flow-change-customer-group-modal__save-button');
        await saveButton.trigger('click');

        expect(wrapper.emitted()['process-finish'][0]).toEqual([{
            config: {
                customerGroupId: '2',
            },
        }]);
    });

    it('should be able to close modal', async () => {
        const wrapper = await createWrapper();

        const cancelButton = wrapper.find('.sw-flow-change-customer-group-modal__cancel-button');
        expect(cancelButton.isVisible()).toBeTruthy();

        await cancelButton.trigger('click');
        expect(wrapper.emitted()['modal-close']).toBeTruthy();
    });
});
