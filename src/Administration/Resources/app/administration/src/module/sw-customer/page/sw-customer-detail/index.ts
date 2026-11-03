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
    name: 'sw-customer-detail',
    load: () => import('./src/sw-customer-detail'),
    public: {
        props: [
            'customerId',
        ],
        blocks: [
            'sw_customer_detail',
            'sw_customer_detail_header',
            'sw_customer_detail_actions',
            'sw_customer_detail_actions_edit',
            'sw_customer_detail_actions_cancel',
            'sw_customer_detail_actions_save',
            'sw_customer_detail_content',
            'sw_customer_detail_content_customer_group_registration',
            'sw_customer_detail_content_customer_group_registration_content',
            'sw_customer_detail_content_customer_group_registration_message',
            'sw_customer_detail_content_customer_group_registration_actions',
            'sw_customer_detail_content_tabs',
            'sw_customer_detail_content_mt_tabs',
            'sw_customer_detail_content_tab_general',
            'sw_customer_detail_content_tab_addresses',
            'sw_customer_detail_content_tab_order',
            'sw_customer_detail_content_tab_after',
            'sw_customer_detail_content_view',
        ],
        computed: [
            'identifier',
            'customerRepository',
            'editMode',
            'defaultCriteria',
            'generalRoute',
            'addressesRoute',
            'ordersRoute',
            'customerDetailTabs',
            'emailHasChanged',
            'validCompanyField',
            'salutationRepository',
            'salutationCriteria',
        ],
        methods: [
            'loadCustomer',
            'createdComponent',
            'saveFinish',
            'validateEmail',
            'onSave',
            'onAbortButtonClick',
            'onActivateCustomerEditMode',
            'abortOnLanguageChange',
            'saveOnLanguageChange',
            'onChangeLanguage',
            'validPassword',
            'acceptCustomerGroupRegistration',
            'declineCustomerGroupRegistration',
            'createErrorMessageForCompanyField',
            'getDefaultSalutation',
        ],
    },
});
