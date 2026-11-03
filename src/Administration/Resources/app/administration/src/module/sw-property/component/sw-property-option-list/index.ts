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
    name: 'sw-property-option-list',
    load: () => import('./src/sw-property-option-list'),
    public: {
        props: [
            'propertyGroup',
            'optionRepository',
        ],
        blocks: [
            'sw_property_option_list',
            'sw_property_option_list_toolbar',
            'sw_property_option_list_toolbar_container',
            'sw_property_option_list_toolbar_search',
            'sw_property_option_list_toolbar_delete',
            'sw_property_option_list_toolbar_add',
            'sw_property_option_list_grid',
            'sw_settings_property_list_grid_columns_actions',
            'sw_settings_property_list_grid_columns_actions_edit',
            'sw_property_list_grid_columns_actions_delete',
            'sw_settings_country_state_list_empty',
            'sw_property_option_list_detail',
            'sw_property_option_list_loader',
        ],
        computed: [
            'isSystemLanguage',
            'currentLanguage',
            'allowInlineEdit',
            'tooltipAdd',
            'disableAddButton',
            'useNaturalNameSorting',
            'dataSource',
        ],
        methods: [
            'onSearch',
            'onGridSelectionChanged',
            'onOptionDelete',
            'onSingleOptionDelete',
            'onDeleteOptions',
            'onAddOption',
            'onCancelOption',
            'onSaveOption',
            'saveGroupLocal',
            'saveGroupRemote',
            'refreshOptionList',
            'resetSelectionState',
            'onOptionEdit',
            'getGroupColumns',
            'checkEmptyState',
        ],
    },
});
