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
    name: 'sw-flow-change-customer-group-modal',
    load: () => import('./src/sw-flow-change-customer-group-modal'),
    public: {
        props: [
            'sequence',
        ],
        emits: [
            'modal-close',
            'process-finish',
        ],
        blocks: [
            'sw_flow_change_customer_group_modal',
            'sw_flow_change_customer_group_modal_select',
            'sw_flow_change_customer_group_modal_content_custom',
            'sw_flow_change_customer_group_modal_footer',
            'sw_flow_change_customer_group_modal_footer_cancel_button',
            'sw_flow_change_customer_group_modal_footer_save_button',
        ],
        computed: [
            'customerGroupRepository',
            'customerGroupCriteria',
        ],
        methods: [
            'createdComponent',
            'onClose',
            'onAddAction',
        ],
    },
});
