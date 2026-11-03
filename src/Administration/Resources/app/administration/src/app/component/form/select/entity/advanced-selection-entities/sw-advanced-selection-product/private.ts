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
    name: 'sw-advanced-selection-product',
    load: () => import('./src/sw-advanced-selection-product'),
    public: {
        emits: [
            'selection-submit',
            'modal-close',
        ],
        blocks: [
            'sw_advanced_selection_product',
            'sw_advanced_selection_product_list_grid_columns_name_preview',
            'sw_advanced_selection_product_list_grid_columns_name',
            'sw_advanced_selection_product_list_grid_columns_name_content',
            'sw_advanced_selection_product_list_grid_columns_active',
            'sw_advanced_selection_product_list_grid_columns_active_content',
            'sw_advanced_selection_product_list_grid_columns_price',
            'sw_advanced_selection_product_list_grid_columns_stock',
            'sw_advanced_selection_product_list_grid_columns_stock_content',
            'sw_advanced_selection_product_list_grid_columns_release_date',
            'sw_advanced_selection_product_list_grid_columns_release_date_content',
            'sw_advanced_selection_product_list_grid_columns_visibilities',
            'sw_advanced_selection_product_list_grid_columns_visibilities_content',
            'sw_advanced_selection_product_list_grid_columns_categories',
            'sw_advanced_selection_product_list_grid_columns_categories_content',
            'sw_advanced_selection_product_list_grid_columns_tags',
            'sw_advanced_selection_product_list_grid_columns_tags_content',
        ],
        computed: [
            'currencyRepository',
            'productContext',
            'currenciesColumns',
            'productColumns',
            'productFilters',
            'productAssociations',
            'currencyFilter',
            'dateFilter',
            'stockColorVariantFilter',
        ],
        methods: [
            'createdComponent',
            'productHasVariants',
            'getCurrencyPriceByCurrencyId',
            'getCategoryBreadcrumb',
        ],
    },
});
