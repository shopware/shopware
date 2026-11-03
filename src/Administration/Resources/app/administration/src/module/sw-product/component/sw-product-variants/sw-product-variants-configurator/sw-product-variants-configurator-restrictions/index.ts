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
    name: 'sw-product-variants-configurator-restrictions',
    load: () => import('./src/sw-product-variants-configurator-restrictions'),
    public: {
        props: [
            'product',
            'selectedGroups',
        ],
        blocks: [
            'sw_product_variants_configurator_restrictions',
            'sw_product_variants_configurator_restrictions_search',
            'sw_product_variants_configurator_restrictions_search_field',
            'sw_product_variants_configurator_restrictions_buttons',
            'sw_product_variants_configurator_restrictions_grid_wrapper',
            'sw_product_variants_configurator_restrictions_data_grid',
            'sw_product_variants_configurator_restrictions_data_grid_column_id',
            'sw_product_variants_configurator_restrictions_data_grid_actions',
            'sw_product_variants_configurator_restrictions_data_grid_actions_items',
            'sw_product_variants_configurator_restrictions_modal',
            'sw_product_variants_configurator_restrictions_modal_main',
            'sw_product_variants_configurator_restrictions_modal_main_restriction_selection',
            'sw_product_variants_configurator_restrictions_modal_main_button_new_restriction',
            'sw_product_variants_configurator_restrictions_modal_footer',
            'sw_product_variants_configurator_restrictions_modal_footer_cancel',
            'sw_product_variants_configurator_restrictions_modal_footer_save_add_restriction',
            'sw_product_variants_configurator_restrictions_loader',
        ],
        computed: [
            'restrictionColumns',
            'actualRestrictionValueLength',
            'filteredRestrictions',
        ],
        methods: [
            'mountedComponent',
            'getOptionsForGroupId',
            'getRestrictionsWithNaming',
            'filterEmptyValues',
            'addEmptyRestrictionCombination',
            'addEmptyRestriction',
            'cancelAddRestriction',
            'saveAddRestriction',
            'editRestrictionCombination',
            'deleteRestrictionCombination',
            'deleteRestriction',
        ],
    },
});
