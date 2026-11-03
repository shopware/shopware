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
    name: 'sw-settings-salutation-detail',
    load: () => import('./src/sw-settings-salutation-detail'),
    public: {
        props: [
            'salutationId',
        ],
        blocks: [
            'sw_settings_salutation_detail',
            'sw_settings_salutation_detail_search_bar',
            'sw_settings_salutation_detail_smart_bar_header',
            'sw_settings_salutation_detail_smart_bar_header_title',
            'sw_settings_salutation_detail_smart_bar_header_title_text',
            'sw_settings_salutation_detail_language_switch',
            'sw_settings_salutation_detail_actions',
            'sw_settings_salutation_detail_actions_cancel',
            'sw_settings_salutation_detail_actions_save',
            'sw_settings_salutation_detail_cardview',
            'sw_settings_salutation_detail_content_language_info',
            'sw_settings_salutation_detail_content_card',
            'sw_settings_salutation_detail_content_card_position',
            'sw_settings_salutation_detail_content_card_salutation_key',
            'sw_settings_salutation_detail_content_card_display_name',
            'sw_settings_salutation_detail_content_card_letter_name',
            'sw_settings_salutation_detail_custom_field_sets',
        ],
        computed: [
            'identifier',
            'salutationRepository',
            'entityDescription',
            'invalidKeyError',
            'allowSave',
            'tooltipSave',
            'tooltipCancel',
            'showCustomFields',
        ],
        methods: [
            'createdComponent',
            'loadCustomFieldSets',
            'onChangeLanguage',
            'saveFinish',
            'onSave',
            'onCancel',
            'onChange',
            'onChangeDebounce',
        ],
    },
});
