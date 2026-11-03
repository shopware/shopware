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
    name: 'sw-order-create-address-modal',
    load: () => import('./src/sw-order-create-address-modal'),
    public: {
        props: [
            'customer',
            'address',
            'addAddressModalTitle',
            'editAddressModalTitle',
            'cart',
        ],
        emits: [
            'set-customer-address',
            'close-modal',
        ],
        blocks: [
            'sw_order_create_address_modal',
            'sw_order_create_address_modal_content',
            'sw_order_create_address_form_modal',
            'sw_order_create_address_form_modal_content',
            'sw_order_create_address_form_modal_footer',
            'sw_order_create_address_form_modal_cancel_button',
            'sw_order_create_address_form_modal_apply_button',
            'sw_order_create_address_modal_toolbar',
            'sw_order_create_address_modal_add_button',
            'sw_order_create_address_modal_empty_state',
            'sw_order_create_address_modal_empty_state_content',
            'sw_order_create_address_modal_actions',
            'sw_order_create_address_modal_cancel_button',
        ],
        computed: [
            'addressCriteria',
            'customerRepository',
            'addressRepository',
            'isValidCompanyField',
        ],
        methods: [
            'createdComponent',
            'getCustomerAddresses',
            'onNewActiveItem',
            'isCurrentSelected',
            'onSearchAddress',
            'onSelectExistingAddress',
            'findSelectedAddress',
            'updateOrderContext',
            'saveCurrentCustomer',
            'saveCurrentAddress',
            'closeModal',
            'onCancel',
            'onSave',
            'onCloseAddressModal',
            'onAddNewAddress',
            'onEditAddress',
            'onChangeDefaultAddress',
            'onSubmitAddressForm',
            'getAddressFormModalTitle',
            'createNewCustomerAddress',
        ],
    },
});
