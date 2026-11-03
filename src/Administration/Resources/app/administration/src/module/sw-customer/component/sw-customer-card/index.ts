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
    name: 'sw-customer-card',
    load: () => import('./src/sw-customer-card'),
    public: {
        props: [
            'customer',
            'title',
            'editMode',
            'isLoading',
        ],
        slots: [
            'metadata-additional',
            'actions',
            'default',
        ],
        blocks: [
            'sw_customer_card',
            'sw_customer_card_header_right',
            'sw_customer_card_convert_guest',
            'sw_customer_card_imitate_customer_modal',
            'sw_customer_card_convert_customer_modal',
            'sw_customer_card_action_customer_impersonation',
            'sw_customer_card_rows',
            'sw_customer_card_row_primary',
            'sw_customer_card_metadata_container',
            'sw_customer_card_metadata',
            'sw_customer_card_avatar',
            'sw_customer_card_metadata_customer_name',
            'sw_custsomer_card_metadata_customer_name_label',
            'sw_customer_card_metadata_customer_email',
            'sw_customer_card_metadata_customer_email_label',
            'sw_customer_card_metadata_customer_name_editor',
            'sw_customer_card_account_type_field',
            'sw_customer_card_metadata_customer_name_editor_salutation',
            'sw_customer_card_metadata_customer_name_editor_title',
            'sw_customer_card_metadata_customer_name_editor_first_name',
            'sw_customer_card_metadata_customer_name_editor_last_name',
            'sw_customer_card_metadata_customer_name_editor_company',
            'sw_customer_card_vat_ids',
            'sw_customer_card_metadata_customer_email_editor',
            'sw_customer_card_password',
            'sw_customer_card_password_confirm',
            'sw_customer_card_metadata_customer_tag',
            'sw_customer_card_metadata_additional',
            'sw_customer_card_slot_metadata_additional',
            'sw_customer_card_actions',
            'sw_customer_card_slot_actions',
            'sw_customer_card_row_secondary',
            'sw_customer_card_slot_default',
        ],
        computed: [
            'hasActionSlot',
            'hasAdditionalDataSlot',
            'hasSummarySlot',
            'moduleColor',
            'fullName',
            'salutationCriteria',
            'accountTypeOptions',
            'isBusinessAccountType',
            'canUseCustomerImitation',
            'canUseConvertCustomer',
            'customerImitationWarning',
            'hasSingleBoundSalesChannelUrl',
            'currentUser',
            'emailIdnFilter',
        ],
        methods: [
            'getMailTo',
            'onImitateCustomer',
            'onCloseImitateCustomerModal',
            'onOpenConvertCustomerModal',
            'onCloseConvertCustomerModal',
        ],
    },
});
