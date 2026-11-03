import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-customer-group-detail',
    load: () => import('./src/sw-settings-customer-group-detail'),
    public: {
        props: [
            'customerGroupId',
        ],
        blocks: [
            'sw_settings_customer_group_detail',
            'sw_settings_customer_group_detail_search_bar',
            'sw_settings_customer_group_detail_smart_bar_header',
            'sw_settings_customer_group_detail_smart_bar_header_title',
            'sw_settings_customer_group_detail_smart_bar_header_title_text',
            'sw_settings_customer_group_detail_language_switch',
            'sw_settings_customer_group_detail_actions',
            'sw_settings_customer_group_detail_actions_cancel',
            'sw_settings_customer_group_detail_actions_save',
            'sw_settings_customer_group_detail_cardview',
            'sw_settings_customer_group_detail_content_language_info',
            'sw_settings_customer_group_detail_content_card',
            'sw_settings_customer_group_detail_content_card_name',
            'sw_settings_customer_group_detail_content_card_display_gross',
            'sw_settings_customer_group_detail_content_card_registration_form',
            'sw_settings_customer_group_detail_content_registration_card',
            'sw_settings_customer_group_detail_content_registration_card_title',
            'sw_settings_customer_group_detail_content_registration_card_introduction',
            'sw_settings_customer_group_detail_content_registration_card_seo_meta_description',
            'sw_settings_customer_group_detail_content_registration_card_only_companies_can_register',
            'sw_settings_customer_group_detail_content_registration_url_card',
            'sw_settings_customer_group_detail_content_registration_url_card_sales_channel',
            'sw_settings_customer_group_detail_content_registration_technical_urls',
            'sw_settings_customer_group_detail_content_registration_url_card_seo_url',
            'sw_settings_customer_group_detail_content_registration_url_card_seo_url_inner',
            'sw_settings_customer_group_detail_custom_field_sets',
        ],
        computed: [
            'identifier',
            'customerGroupRepository',
            'seoUrlRepository',
            'customerGroupCriteria',
            'registrationSalesChannelCriteria',
            'seoUrlCriteria',
            'entityDescription',
            'tooltipSave',
            'tooltipCancel',
            'hasRegistration',
            'technicalUrl',
            'allowSave',
            'showCustomFields',
        ],
        methods: [
            'createdComponent',
            'loadCustomerGroup',
            'loadSeoUrls',
            'loadCustomFieldSets',
            'onChangeLanguage',
            'onCancel',
            'getSeoUrl',
            'validateSaveRequest',
            'onSave',
        ],
    },
});
