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
    name: 'sw-settings-delivery-time-detail',
    load: () => import('./src/sw-settings-delivery-time-detail'),
    public: {
        blocks: [
            'sw_settings_delivery_time_detail',
            'sw_settings_delivery_time_detail_smart_bar_header',
            'sw_settings_delivery_time_detail_language_switch',
            'sw_settings_delivery_time_detail_smart_bar_actions',
            'sw_settings_delivery_time_detail_smart_bar_actions_cancel',
            'sw_settings_delivery_time_detail_smart_bar_actions_save',
            'sw_settings_delivery_time_detail_content',
            'sw_settings_delivery_time_language_info',
            'sw_settings_delivery_time_detail_form',
            'sw_settings_delivery_time_detail_name_field',
            'sw_settings_delivery_time_detail_unit_field',
            'sw_settings_delivery_time_min_max_container',
            'sw_settings_delivery_time_detail_min_field',
            'sw_settings_delivery_time_detail_max_field',
            'sw_settings_delivery_time_detail_custom_field_sets',
        ],
        computed: [
            'deliveryTimeRepository',
            'deliveryTimeUnits',
            'displayName',
            'isInvalidMinField',
            'invalidMinError',
            'allowSave',
            'tooltipSave',
            'tooltipCancel',
            'showCustomFields',
        ],
        methods: [
            'createdComponent',
            'loadCustomFieldSets',
            'onSave',
            'onChangeLanguage',
            'saveFinish',
            'onCancel',
        ],
    },
});
