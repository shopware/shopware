import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-property-detail',
    load: () => import('./src/sw-property-detail'),
    public: {
        props: [
            'groupId',
        ],
        blocks: [
            'sw_property_detail',
            'sw_property_detail_smart_bar_header',
            'sw_property_detail_smart_bar_header_title',
            'sw_property_detail_smart_bar_actions',
            'sw_property_detail_smart_bar_actions_cancel',
            'sw_property_detail_smart_bar_actions_save',
            'sw_property_detail_language_switch',
            'sw_property_detail_content',
            'sw_property_detail_content_language_info',
            'sw_property_detail_content_detail_base',
            'sw_property_detail_content_option_list',
            'sw_property_detail_custom_field_sets',
        ],
        computed: [
            'identifier',
            'optionRepository',
            'propertyRepository',
            'tooltipSave',
            'tooltipCancel',
            'defaultCriteria',
            'useNaturalSorting',
            'showCustomFields',
        ],
        methods: [
            'createdComponent',
            'loadEntityData',
            'loadCustomFieldSets',
            'saveFinish',
            'saveOnLanguageChange',
            'abortOnLanguageChange',
            'onChangeLanguage',
            'onSave',
            'onCancel',
        ],
    },
});
