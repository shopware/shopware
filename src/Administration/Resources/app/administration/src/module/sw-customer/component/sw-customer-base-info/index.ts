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
    name: 'sw-customer-base-info',
    load: () => import('./src/sw-customer-base-info'),
    public: {
        props: [
            'customer',
            'customerEditMode',
            'isLoading',
        ],
        blocks: [
            'sw_customer_base_info',
            'sw_customer_base_info_metadata',
            'sw_customer_base_info_metadata_left',
            'sw_customer_base_metadata_customer_group',
            'sw_customer_base_metadata_customer_group_label',
            'sw_customer_base_metadata_customer_group_content',
            'sw_customer_base_metadata_customer_group_editor',
            'sw_customer_base_metadata_active',
            'sw_customer_base_metadata_active_label',
            'sw_customer_base_metadata_active_content',
            'sw_customer_base_metadata_active_editor',
            'sw_customer_base_metadata_confirmed',
            'sw_customer_base_metadata_confirmed_label',
            'sw_customer_base_metadata_confirmed_content',
            'sw_customer_base_metadata_confirmed_editor',
            'sw_customer_base_metadata_language',
            'sw_customer_base_metadata_language_label',
            'sw_customer_base_metadata_language_content',
            'sw_customer_base_metadata_language_editor',
            'ssw_customer_base_metadata_affiliate',
            'sw_customer_base_metadata_campaign',
            'sw_customer_base_info_metadata_right',
            'sw_customer_base_metadata_customer_number',
            'sw_customer_base_metadata_bound_sales_channel',
            'sw_customer_base_metadata_bound_sales_channel_label',
            'sw_customer_base_metadata_bound_saleschannel_content',
            'sw_customer_base_metadata_last_login',
            'sw_customer_base_metadata_turnover',
            'sw_customer_base_metadata_orders',
            'sw_customer_base_metadata_birthday',
            'sw_customer_base_metadata_birthday_label',
            'sw_customer_base_metadata_birthday_content',
            'sw_custoemr_base_metadata_birthday_editor',
        ],
        computed: [
            'orderRepository',
            'languageRepository',
            'languageId',
            'customerLanguageName',
            'languageCriteria',
            'orderCriteria',
            'isBusinessAccountType',
            'dateFilter',
            'currencyFilter',
        ],
        methods: [
            'createdComponent',
        ],
    },
});
