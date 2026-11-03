import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-flow-set-order-state-modal',
    load: () => import('./src/sw-flow-set-order-state-modal'),
    public: {
        props: [
            'sequence',
        ],
        emits: [
            'modal-close',
            'process-finish',
        ],
        blocks: [
            'sw_flow_set_order_state_modal',
            'sw_flow_set_order_state_modal_content',
            'sw_flow_set_order_state_modal_payment_status',
            'sw_flow_set_order_state_modal_delivery_status',
            'sw_flow_set_order_state_modal_order_status',
            'sw_flow_set_order_state_modal_force_transition',
            'sw_flow_set_order_state_modal_footer',
            'sw_flow_set_order_state_modal_footer_cancel_button',
            'sw_flow_set_order_state_modal_footer_save_button',
        ],
        computed: [
            'stateMachineStateRepository',
            'stateMachineStateCriteria',
        ],
        methods: [
            'createdComponent',
            'getAllStates',
            'generateOptions',
            'buildTransitionOptions',
            'onClose',
            'onAddAction',
        ],
    },
});
