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
    name: 'sw-order-new-customer-modal',
    load: () => import('./src/sw-order-new-customer-modal'),
    public: {
        emits: [
            'on-select-existing-customer',
            'close',
        ],
        blocks: [
            'sw_order_new_customer_modal',
            'sw_order_new_customer_modal_tabs',
            'sw_order_new_customer_modal_mt_tabs',
            'sw_order_new_customer_modal_mt_tabs_content',
            'sw_order_new_customer_modal_mt_tabs_content_details',
            'sw_order_new_customer_modal_mt_tabs_content_details_guest',
            'sw_order_new_customer_modal_mt_tabs_content_details_form',
            'sw_order_new_customer_modal_mt_tabs_content_shipping',
            'sw_order_new_customer_modal_mt_tabs_content_shipping_same_billing',
            'sw_order_new_customer_modal_mt_tabs_content_shipping_form',
            'sw_order_new_customer_modal_mt_tabs_content_billing',
            'sw_order_new_customer_modal_mt_tabs_content_billing_form',
            'sw_order_new_customer_modal_tabs_details',
            'sw_order_new_customer_modal_tabs_billing',
            'sw_order_new_customer_modal_tabs_shipping',
            'sw_order_new_customer_modal_content_details',
            'sw_order_new_customer_modal_content_details_guest',
            'sw_order_new_customer_modal_content_details_form',
            'sw_order_new_customer_modal_content_shipping',
            'sw_order_new_customer_modal_content_shipping_same_billing',
            'sw_order_new_customer_modal_content_shipping_form',
            'sw_order_new_customer_modal_content_billing',
            'sw_order_new_customer_modal_content_billing_form',
            'sw_order_new_customer_modal_actions',
            'sw_order_new_customer_modal_action_close',
            'sw_order_new_customer_modal_actions_apply',
        ],
        computed: [
            'customerRepository',
            'addressRepository',
            'newCustomerModalTabs',
            'shippingAddress',
            'billingAddress',
            'isSameBilling',
            'validCompanyField',
            'languageRepository',
            'languageCriteria',
            'languageId',
            'salutationRepository',
            'salutationCriteria',
        ],
        methods: [
            'createdComponent',
            'onSave',
            'saveCustomer',
            'onChangeSalesChannel',
            'onClose',
            'clearOwnApiErrors',
            'createErrorMessageForCompanyField',
            'validateEmail',
            'loadLanguage',
            'getDefaultSalutationId',
        ],
    },
});
