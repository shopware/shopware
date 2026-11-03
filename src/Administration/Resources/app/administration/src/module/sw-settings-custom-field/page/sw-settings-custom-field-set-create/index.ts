import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module and what it inherits from sw-settings-custom-field-set-detail,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-custom-field-set-create',
    extends: 'sw-settings-custom-field-set-detail',
    load: () => import('./src/sw-settings-custom-field-set-create'),
    public: {
        blocks: [
            'sw_settings_custom_field_set_detail_content_detail_custom_field_list',
            'sw_settings_custom_field_set_detail_content_detail_custom_field_list_empty_state',
            'sw_settings_custom_field_set_detail',
            'sw_settings_customField_set_detail_header',
            'sw_settings_custom_field_set_detail_actions',
            'sw_settings_custom_field_set_detail_actions_abort',
            'sw_settings_custom_field_set_detail_actions_save',
            'sw_settings_custom_field_set_detail_content',
            'sw_settings_custom_field_set_detail_content_detail_base',
        ],
        computed: [
            'identifier',
            'customFieldSetRepository',
            'customFieldRepository',
            'customFieldCriteria',
            'customFieldSetCriteria',
            'tooltipSave',
            'tooltipCancel',
        ],
        methods: [
            'createdComponent',
            'saveFinish',
            'onSave',
            'createNameNotUniqueNotification',
            'loadEntityData',
            'onCancel',
            'abortOnLanguageChange',
            'saveOnLanguageChange',
            'onChangeLanguage',
            'onLoadingChanged',
            'onResetErrors',
        ],
    },
});
