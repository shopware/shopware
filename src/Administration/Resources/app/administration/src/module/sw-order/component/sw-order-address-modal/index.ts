import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-order-address-modal',
    load: () => import('./src/sw-order-address-modal'),
    public: {
        props: [
            'address',
            'countries',
            'order',
            'versionContext',
        ],
        emits: [
            'reset',
            'address-select',
            'save',
        ],
        blocks: [
            'sw_order_address_modal',
            'sw_order_address_modal_content',
            'sw_order_address_modal_mt_tabs',
            'sw_order_address_modal_mt_tabs_content',
            'sw_order_address_modal_mt_tabs_content_edit_address',
            'sw_order_address_modal_mt_tabs_content_select_address',
            'sw_order_address_modal_tabs',
            'sw_order_address_modal_tab_edit_address',
            'sw_order_address_modal_tab_select_address',
            'sw_order_address_modal_tabs_content',
            'sw_order_address_modal_tabs_content_edit_address',
            'sw_order_address_modal_tabs_content_select_address',
            'sw_order_address_modal_actions',
            'sw_order_address_modal_action_close',
            'sw_order_address_modal_actions_apply',
        ],
        computed: [
            'customerCriteria',
            'customFieldSetCriteria',
            'customerRepository',
            'orderRepository',
            'orderCustomer',
            'customFieldSetRepository',
            'salutationFilter',
            'addressModalTabs',
        ],
        methods: [
            'createdComponent',
            'getCustomerInfo',
            'onNewActiveItem',
            'addressButtonClasses',
            'onExistingAddressSelected',
            'onClose',
            'onSave',
            'getCustomFieldSetData',
        ],
    },
});
