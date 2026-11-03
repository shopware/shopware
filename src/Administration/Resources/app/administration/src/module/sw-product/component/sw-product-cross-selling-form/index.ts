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
    name: 'sw-product-cross-selling-form',
    load: () => import('./src/sw-product-cross-selling-form'),
    public: {
        props: [
            'crossSelling',
            'allowEdit',
        ],
        blocks: [
            'sw_product_cross_selling_form',
            'sw_prduct_cross_selling_form_card',
            'sw_prduct_cross_selling_form_context_menu',
            'sw_product_cross_selling_form_context_button_delete',
            'sw_prduct_cross_selling_form_title_field',
            'sw_prduct_cross_selling_form_active_field',
            'sw_prduct_cross_selling_form_type',
            'sw_prduct_cross_selling_form_position_field',
            'sw_prduct_cross_selling_form_product_stream_field',
            'sw_prduct_cross_selling_form_sort_field',
            'sw_prduct_cross_selling_form_limit_field',
            'sw_prduct_cross_selling_form_preview_link',
            'sw_product_detail_cross_selling_assignment',
            'sw_product_detail_cross_selling_modal_preview_modal',
            'sw_product_detail_cross_selling_form_modal_delete',
            'sw_product_detail_cross_selling_form_delete_text',
            'sw_product_detail_cross_selling_form_modal_delete_footer',
            'sw_product_detail_cross_selling_modal_delete_action_cancel',
            'sw_product_detail_cross_selling_form_modal_delete_action_delete',
            'sw_product_cross_selling_form_condition_tree_invisibly',
        ],
        computed: [
            'product',
            'isLoading',
            'productCrossSellingRepository',
            'productStreamRepository',
            'productStreamFilterRepository',
            'productStreamFilterCriteria',
            'crossSellingAssigmentRepository',
            'crossSellingTitle',
            'sortingTypes',
            'crossSellingTypes',
            'previewDisabled',
            'sortingConCat',
            'disablePositioning',
            'associationValue',
            'crossSellingTypeOptions',
            'sortingTypeOptions',
            'productStreamCriteria',
        ],
        methods: [
            'createdComponent',
            'onShowDeleteModal',
            'onCloseDeleteModal',
            'onConfirmDelete',
            'openModalPreview',
            'closeModalPreview',
            'loadStreamPreview',
            'getProductStreamFilter',
            'updateProductStreamFilterTree',
            'onSortingChanged',
            'onTypeChanged',
        ],
    },
});
