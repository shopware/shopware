import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-review-detail',
    load: () => import('./src/sw-review-detail'),
    public: {
        slots: [
            'default',
        ],
        blocks: [
            'sw_review_detail',
            'sw_review_detail_header',
            'sw_review_detail_actions',
            'sw_review_detail_actions_abort',
            'sw_review_detail_actions_save',
            'sw_review_detail_content',
            'sw_review_detail_basic_info_card',
            'sw_customer_card_rows',
            'sw_customer_card_row_primary',
            'sw_customer_card_metadata_container',
            'sw_customer_card_metadata',
            'sw_customer_card_metadata_customer_name',
            'sw_custsomer_card_metadata_customer_name_label',
            'sw_customer_card_row_secondary',
            'sw_customer_base_metadata_created_at',
            'sw_customer_base_metadata_created_at_label',
            'sw_customer_base_metadata_created_at_content',
            'sw_customer_base_metadata_sales_channel',
            'sw_customer_base_metadata_sales_channel_label',
            'sw_customer_base_metadata_sales_channel_content',
            'sw_customer_base_metadata_product',
            'sw_customer_base_metadata_product_label',
            'sw_customer_base_metadata_product_content',
            'sw_customer_base_metadata_customer',
            'sw_customer_base_metadata_customer_label',
            'sw_customer_base_metadata_customer_content',
            'sw_customer_base_metadata_email',
            'sw_customer_base_metadata_email_label',
            'sw_customer_base_metadata_email_content',
            'sw_review_detail_basic_info_card_description_list',
            'sw_review_detail_description_list_language',
            'sw_review_detail_description_list_status',
            'sw_review_detail_basic_info_card_comment',
            'sw_review_detail_custom_field_sets',
        ],
        computed: [
            'identifier',
            'repository',
            'stars',
            'languageCriteria',
            'tooltipSave',
            'tooltipCancel',
            'showCustomFields',
            'dateFilter',
            'emailIdnFilter',
        ],
        methods: [
            'createdComponent',
            'loadEntityData',
            'loadCustomFieldSets',
            'onSave',
            'onSaveFinish',
            'onCancel',
        ],
    },
});
