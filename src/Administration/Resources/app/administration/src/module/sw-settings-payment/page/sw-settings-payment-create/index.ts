import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module and what it inherits from sw-settings-payment-detail,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-payment-create',
    extends: 'sw-settings-payment-detail',
    load: () => import('./src/sw-settings-payment-create'),
    public: {
        blocks: [
            'sw_settings_payment_detail_language_switch',
            'sw_product_stream_detail_content_language_info',
            'sw_settings_payment_detail',
            'sw_settings_payment_detail_header',
            'sw_settings_payment_detail_actions',
            'sw_settings_payment_detail_actions_abort',
            'sw_settings_payment_detail_actions_save',
            'sw_settings_payment_detail_content',
            'sw_settings_payment_detail_content_card',
            'sw_settings_payment_detail_content_field_plugin',
            'sw_settings_payment_detail_content_field_name',
            'sw_settings_payment_detail_content_field_technical_name',
            'sw_settings_payment_detail_content_field_position',
            'sw_settings_payment_detail_base_content_field_description',
            'sw_settings_payment_detail_base_content_media_upload',
            'sw_settings_payment_detail_content_field_active',
            'sw_settings_payment_detail_content_field_after_order_enabled',
            'sw_settings_payment_detail_top_rule',
            'sw_settings_payment_detail_top_rule_select',
            'sw_settings_payment_detail_custom_field_sets',
            'sw_settings_payment_detail_delete_payment_method',
            'sw_settings_payment_detail_delete_modal',
            'sw_settings_payment_detail_delete_modal_info',
            'sw_settings_payment_detail_delete_modal_footer',
            'sw_settings_payment_detail_reset_modal_cancel',
            'sw_settings_payment_detail_reset_modal_confirm',
        ],
        computed: [
            'isNewPaymentMethod',
            'identifier',
            'paymentMethodRepository',
            'ruleRepository',
            'mediaRepository',
            'tooltipSave',
            'tooltipCancel',
            'ruleFilter',
            'showCustomFields',
            'paymentMethodCriteria',
            'forbidDelete',
            'technicalNameIsProvided',
        ],
        methods: [
            'createdComponent',
            'onSave',
            'onSaveRule',
            'onDismissRule',
            'abortOnLanguageChange',
            'saveOnLanguageChange',
            'onChangeLanguage',
            'loadEntityData',
            'loadCustomFieldSets',
            'saveFinish',
            'onError',
            'onCancel',
            'setMediaItem',
            'setMediaFromSidebar',
            'onUnlinkLogo',
            'onDropMedia',
            'openMediaSidebar',
            'deletePaymentMethod',
        ],
    },
});
