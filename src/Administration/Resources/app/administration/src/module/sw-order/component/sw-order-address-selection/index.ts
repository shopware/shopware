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
    name: 'sw-order-address-selection',
    load: () => import('./src/sw-order-address-selection'),
    public: {
        props: [
            'address',
            'label',
            'addressId',
            'disabled',
            'type',
        ],
        emits: [
            'change-address',
        ],
        blocks: [
            'sw_order_address_selection',
            'sw_order_address_selection_modal',
            'sw_order_address_modal_content',
            'sw_order_address_modal_actions',
            'sw_order_address_modal_action_close',
            'sw_order_address_modal_actions_apply',
            'sw_order_address_selection_entity_single_select',
            'sw_order_address_selection_selected_address',
        ],
        computed: [
            'order',
            'versionContext',
            'orderCustomer',
            'customer',
            'orderRepository',
            'addressRepository',
            'customerRepository',
            'customerCriteria',
            'customFieldSetRepository',
            'customFieldSetCriteria',
            'addressOptions',
            'modalTitle',
            'selectedAddressId',
            'selectedAddress',
        ],
        methods: [
            'createdComponent',
            'onEditAddress',
            'onCreateNewAddress',
            'createNewCustomerAddress',
            'onSaveAddress',
            'isValidAddress',
            'clearAddressErrors',
            'removeRequiredFieldError',
            'onChangeDefaultAddress',
            'createPrefix',
            'onAddressChange',
            'getCustomer',
            'getCustomFieldSet',
            'renderSelectedAddress',
            'addressLabel',
        ],
    },
});
