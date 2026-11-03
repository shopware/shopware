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
    name: 'sw-product-detail-reviews',
    load: () => import('./src/sw-product-detail-reviews'),
    public: {
        blocks: [
            'sw_product_detail_reviews',
            'sw_product_detail_reviews_data',
            'sw_product_detail_reviews_data_stars',
            'sw_product_detail_reviews_data_stars_content',
            'sw_product_detail_reviews_data_status',
            'sw_product_detail_reviews_data_created_at',
            'sw_product_detail_reviews_data_action',
            'sw_product_detail_reviews_data_action_edit',
            'sw_product_detail_reviews_data_action_delete',
            'sw_product_detail_reviews_data_pagination',
            'sw_product_detail_reviews_empty_state',
            'sw_product_detail_reviews_empty_state_button',
            'sw_product_detail_reviews_modal_delete',
            'sw_product_detail_reviews_modal_delete_body',
            'sw_product_detail_reviews_modal_delete_footer',
            'sw_product_detail_reviews_modal_delete_button_cancel',
            'sw_product_detail_reviews_modal_delete_button_confirm',
        ],
        computed: [
            'product',
            'isLoading',
            'cardTitle',
            'reviewRepository',
            'reviewCriteria',
            'reviewColumns',
            'assetFilter',
            'dateFilter',
        ],
        methods: [
            'createdComponent',
            'getReviews',
            'onStartReviewDelete',
            'onCancelReviewDelete',
            'onShowReviewDeleteModal',
            'onCloseReviewDeleteModal',
            'onConfirmReviewDelete',
            'onChangePage',
        ],
    },
});
