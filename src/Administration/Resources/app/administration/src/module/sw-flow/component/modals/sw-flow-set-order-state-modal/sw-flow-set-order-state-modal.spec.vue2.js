import { createLocalVue, shallowMount } from '@vue/test-utils_v2';
import swFlowSetOrderStateModal from 'src/module/sw-flow/component/modals/sw-flow-set-order-state-modal';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/field-base/sw-base-field';

import Vuex from 'vuex_v2';
import flowState from 'src/module/sw-flow/state/flow.state';

Shopware.Component.register('sw-flow-set-order-state-modal', swFlowSetOrderStateModal);

const stateMachineStateMock = [
    {
        technicalName: 'paid',
        translated: { name: 'Paid' },
        stateMachine: {
            technicalName: 'order_transaction.state',
        },
    },
    {
        technicalName: 'shipped',
        translated: { name: 'Shipped' },
        stateMachine: {
            technicalName: 'order_delivery.state',
        },
    },
    {
        technicalName: 'in_progress',
        translated: { name: 'In progress' },
        stateMachine: {
            technicalName: 'order.state',
        },
    },
];

async function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(await Shopware.Component.build('sw-flow-set-order-state-modal'), {
        localVue,
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        search: () => Promise.resolve(stateMachineStateMock),
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
            'sw-icon': true,
            'sw-select-field': {
                model: {
                    prop: 'value',
                    event: 'change',
                },
                template: `
                    <select class="sw-select-field"
                            :value="value"
                            @change="$emit('change', $event.target.value)">
                        <option
                            v-for="option in options"
                            :key="option.id"
                            :value="option.id"
                        >
                            {{ option.name }}
                        </option>
                    </select>`,
                props: ['value', 'options'],
            },
            'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-help-text': true,
            'sw-field-error': true,
        },
    });
}

describe('module/sw-flow/component/sw-flow-set-order-state-modal', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', flowState);
    });

    it('should show error notification if no field is selected', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.createNotificationError = jest.fn();

        const saveButton = wrapper.find('.sw-flow-set-order-state-modal__save-button');
        await saveButton.trigger('click');

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();
    });

    it('should emit process-finish when selecting at least 1 status', async () => {
        const wrapper = await createWrapper();

        const paymentSelect = wrapper.find('.sw-flow-set-order-state-modal__payment-status');
        await paymentSelect.setValue('paid');

        const deliverySelect = wrapper.find('.sw-flow-set-order-state-modal__delivery-status');
        await deliverySelect.setValue('shipped');

        const orderSelect = wrapper.find('.sw-flow-set-order-state-modal__order-status');
        await orderSelect.setValue('in_progress');

        const forceTransitionCheckBox = wrapper.find('.sw-flow-set-order-state-modal__force-transition input');
        await forceTransitionCheckBox.setChecked(true);

        const saveButton = wrapper.find('.sw-flow-set-order-state-modal__save-button');
        await saveButton.trigger('click');

        expect(wrapper.emitted()['process-finish'][0]).toEqual([{
            config: {
                order: 'in_progress',
                order_delivery: 'shipped',
                order_transaction: 'paid',
                force_transition: true,
            },
        }]);
    });
});
