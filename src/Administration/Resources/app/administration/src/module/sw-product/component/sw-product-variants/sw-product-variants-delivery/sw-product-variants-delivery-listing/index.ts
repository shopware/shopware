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
    name: 'sw-product-variants-delivery-listing',
    load: () => import('./src/sw-product-variants-delivery-listing'),
    public: {
        props: [
            'product',
            'selectedGroups',
        ],
        slots: [
            'selection-label-property',
            'result-item',
        ],
        blocks: [
            'sw_product_variants_delivery_listing',
            'sw_product_variants_delivery_listing_mode',
            'sw_product_variants_delivery_listing_mode_selection',
            'sw_product_variants_delivery_listing_config',
            'sw_product_variants_delivery_listing_config_single',
            'sw_product_variants_delivery_variant_mode_selection',
            'sw_product_variants_delivery_listing_config_single_main_variant_label',
            'sw_product_variants_delivery_listing_config_single_main_variant_select',
            'sw_product_variants_results_list_result_label',
            'sw_product_variants_delivery_listing_config_single_main_variant_explanation',
            'sw_product_variants_delivery_listing_config_expanded',
            'sw_product_variants_delivery_listing_config_expanded_group_list',
            'sw_product_variants_delivery_listing_config_expanded_group_list_items',
            'sw_product_variants_delivery_listing_config_expanded_group_list_item',
        ],
        computed: [
            'listingModeOptions',
            'listingMode',
            'mainVariantModeOptions',
            'variantMode',
            'mainVariant',
            'variantCriteria',
            'context',
            'selectedGroupsSorted',
        ],
        methods: [
            'createdComponent',
            'updateListingMode',
            'updateVariantMode',
            'updateMainVariant',
            'isActiveGroupInListing',
            'onChangeGroupListing',
            'isActiveListingMode',
            'isDisabledListingMode',
            'isSelected',
            'onSearchTermChange',
            'onSelectCollapsed',
        ],
    },
});
