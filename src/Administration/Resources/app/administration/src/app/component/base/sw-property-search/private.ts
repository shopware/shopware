import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-property-search',
    load: () => import('./src/sw-property-search'),
    public: {
        props: [
            'collapsible',
            'overlay',
            'options',
            'isAddOnly',
        ],
        emits: [
            'option-select',
        ],
        slots: [
            'toolbar',
            'toolbar-search-field',
            'toolbar-items',
        ],
        blocks: [
            'sw_property_search',
            'sw_property_search_inner_before',
            'sw_property_search_field',
            'sw_property_toolbar_items',
            'sw_property_search_tree',
            'sw_property_search_tree_container',
            'sw_property_search_tree_group_grid',
            'sw_property_search_tree_group_grid_columns',
            'sw_property_search_tree_group_grid_columns_name',
            'sw_property_search_tree_group_grid_columns_options',
            'sw_property_search_tree_group_grid_pagination',
            'sw_property_search_tree_option_grid',
            'sw_property_search_tree_option_grid_columns',
            'sw_property_search_tree_option_grid_columns_name',
            'sw_property_search_tree_option_grid_pagination',
            'sw_property_search_option_search',
            'sw_property_search_option_search_grid',
            'sw_property_search_option_search_grid_columns',
            'sw_property_search_option_search_grid_columns_name',
            'sw_property_search_option_search_grid_pagination',
            'sw_property_search_empty',
            'sw_property_search_empty_image',
            'sw_property_search_inner_after',
        ],
        computed: [
            'swPropertySearchClasses',
            'propertyGroupRepository',
            'propertyGroupCriteria',
            'propertyGroupOptionRepository',
            'propertyGroupOptionCriteria',
            'assetFilter',
        ],
        methods: [
            'createdComponent',
            'destroyedComponent',
            'selectGroup',
            'onOptionSelect',
            'onGroupPageChange',
            'onOptionPageChange',
            'onOptionSearchPageChange',
            'onFocusSearch',
            'onSearchOptions',
            'closeOnClickOutside',
            'selectOptions',
            'showSearch',
            'showTree',
            'loadGroups',
            'loadOptions',
            'sortOptions',
            'refreshSelection',
            'addOptionCount',
        ],
    },
});
