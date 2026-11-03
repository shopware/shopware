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
    name: 'sw-product-detail-cross-selling',
    load: () => import('./src/sw-product-detail-cross-selling'),
    public: {
        props: [
            'allowEdit',
        ],
        blocks: [
            'sw_product_detail_cross_selling',
            'sw_product_detail_cross_selling_items_wrapper',
            'sw_product_detail_cross_selling_restore_inheritance',
            'sw_product_detail_cross_selling_items',
            'sw_product_detail_cross_selling_add',
            'sw_product_detail_cross_selling_empty_state_card',
            'sw_product_detail_cross_selling_empty_state',
            'sw_product_detail_cross_selling_empty_state_inherit_switch',
            'sw_product_detail_cross_selling_empty_state_actions_add',
            'sw_product_detail_cross_selling_empty_state_actions',
            'sw_product_detail_cross_selling_empty_state_icon',
            'sw_product_detail_cross_selling_empty_state_content',
            'sw_product_detail_cross_selling_empty_state_content_child',
            'sw_product_detail_cross_selling_empty_state_content_child_inherited',
            'sw_product_detail_cross_selling_empty_state_content_child_inherited_link',
            'sw_product_detail_cross_selling_empty_state_content_child_not_inherited',
            'sw_product_detail_cross_selling_empty_state_content_empty',
            'sw_product_detail_cross_selling_modal_restore_inheritance',
            'sw_product_detail_cross_selling_modal_restore_inheritance_text',
            'sw_product_detail_cross_selling_modal_restore_inheritance_footer',
            'sw_product_detail_cross_selling_modal_restore_inheritance_action_cancel',
            'sw_product_detail_cross_selling_modal_restore_inheritance_action_restore',
        ],
        computed: [
            'assetFilter',
            'product',
            'isChild',
            'isLoading',
            'isSystemDefaultLanguage',
            'showCrossSellingCard',
            'onAddCrossSellingTooltipMessage',
            'emptyStateDescription',
            'parentCrossSellingHref',
            'crossSellingRepository',
        ],
        methods: [
            'mountedComponent',
            'loadAssignedProducts',
            'onAddCrossSelling',
            'restoreInheritance',
            'removeInheritance',
            'onShowRestoreInheritanceModal',
            'onCloseRestoreInheritanceModal',
            'onConfirmRestoreInheritance',
        ],
    },
});
