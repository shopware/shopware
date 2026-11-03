import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module and what it inherits from sw-property-search,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-product-variants-configurator-selection',
    extends: 'sw-property-search',
    load: () => import('./src/sw-product-variants-configurator-selection'),
    public: {
        props: [
            'product',
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
            'sw_property_search_field',
            'sw_product_variants_configurator_selection_search_field',
            'sw_property_search',
            'sw_property_search_inner_before',
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
            'configuratorSettingsRepository',
            'swPropertySearchClasses',
            'propertyGroupRepository',
            'propertyGroupCriteria',
            'propertyGroupOptionRepository',
            'propertyGroupOptionCriteria',
            'assetFilter',
        ],
        methods: [
            'addOptionCount',
            'selectOptions',
            'onOptionSelect',
            'createdComponent',
            'destroyedComponent',
            'selectGroup',
            'onGroupPageChange',
            'onOptionPageChange',
            'onOptionSearchPageChange',
            'onFocusSearch',
            'onSearchOptions',
            'closeOnClickOutside',
            'showSearch',
            'showTree',
            'loadGroups',
            'loadOptions',
            'sortOptions',
            'refreshSelection',
        ],
    },
});
