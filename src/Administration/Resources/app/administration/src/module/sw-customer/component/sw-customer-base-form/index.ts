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
    name: 'sw-customer-base-form',
    load: () => import('./src/sw-customer-base-form'),
    public: {
        props: [
            'customer',
        ],
        emits: [
            'sales-channel-change',
        ],
        blocks: [
            'sw_customer_base_form',
            'sw_customer_base_form_account_type_field',
            'sw_customer_base_form_first_salutation_field',
            'sw_customer_base_form_title_field',
            'sw_customer_base_form_first_name_field',
            'sw_customer_base_form_last_name_field',
            'sw_customer_base_form_email_field',
            'sw_customer_base_form_password_field',
            'sw_customer_base_form_vat_id_field',
            'sw_customer_base_form_customer_group_field',
            'sw_customer_base_form_sales_channel_field',
            'sw_customer_base_form_customer_number_field',
            'sw_customer_base_form_birthday_field',
            'sw_customer_base_form_language_field',
            'sw_customer_base_form_tag_field',
        ],
        computed: [
            'salutationCriteria',
            'accountTypeOptions',
            'isBusinessAccountType',
            'languageCriteria',
        ],
        methods: [
            'onSalesChannelChange',
        ],
    },
});
