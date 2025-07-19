import { mount } from '@vue/test-utils';
import selectMtSelectOptionByText from 'test/_helper_/select-mt-select-by-text';

/**
 * @sw-package after-sales
 */

const stateMachineStateMock = [
    {
        technicalName: 'paid',
        translated: { name: 'Paid' },
        stateMachine: {
            technicalName: 'order_transaction.state',
        },
    },
    {
        technicalName: 'open',
        translated: { name: 'Open' },
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
    return mount(
        await wrapTestComponent('sw-flow-set-order-state-modal', {
            sync: true,
        }),
        {
            props: {
                sequence: {},
            },

            global: {
                mocks: {
                    $i18n: {
                        locale: 'en-US',
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
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                    'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    transition: {
                        template: `<div class="transition"><slot/></div>`,
                    },
                    'sw-help-text': true,
                    'sw-field-error': true,
                    'sw-loader': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                },

                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(stateMachineStateMock),
                            };
                        },
                    },
                },
            },
        },
    );
}

describe('module/sw-flow/component/sw-flow-set-order-state-modal', () => {
    it('should show error notification if no field is selected', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.createNotificationError = jest.fn();

        const saveButton = wrapper.find('.sw-flow-set-order-state-modal__save-button');
        await saveButton.trigger('click');

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();
    });

    it('should emit process-finish when selecting at least 1 status', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await selectMtSelectOptionByText(wrapper, 'Paid', '.sw-flow-set-order-state-modal__payment-status input');
        await selectMtSelectOptionByText(wrapper, 'Shipped', '.sw-flow-set-order-state-modal__delivery-status input');
        await selectMtSelectOptionByText(wrapper, 'In progress', '.sw-flow-set-order-state-modal__order-status input');

        const forceTransitionCheckBox = wrapper.find('.sw-flow-set-order-state-modal__force-transition input');
        await forceTransitionCheckBox.setChecked(true);

        const saveButton = wrapper.find('.sw-flow-set-order-state-modal__save-button');
        await saveButton.trigger('click');
        await flushPromises();

        expect(wrapper.emitted()['process-finish'][0]).toEqual([
            {
                config: {
                    order: 'in_progress',
                    order_delivery: 'shipped',
                    order_transaction: 'paid',
                    force_transition: true,
                },
            },
        ]);
    });
});
