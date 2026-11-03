import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-review-list',
    load: () => import('./src/sw-review-list'),
    public: {
        blocks: [
            'sw_review_list',
            'sw_review_list_search_bar',
            'sw_review_list_smart_bar_header',
            'sw_review_list_smart_bar_header_title',
            'sw_review_list_smart_bar_header_title_text',
            'sw_review_list_smart_bar_header_amount',
            'sw_review_list_content',
            'sw_review_list_empty_state',
            'sw_review_list_content_list',
            'sw_review_list_content_list_status',
            'sw_review_list_content_list_title',
            'sw_review_list_content_list_created_at',
            'sw_review_list_content_list_user',
            'sw_review_list_content_list_product',
            'sw_review_list_content_list_points',
            'sw_review_list_content_list_comment',
            'sw_review_list_sidebar',
            'sw_review_list_sidebar_refresh',
            'sw_review_list_sidebar_filter',
        ],
        computed: [
            'listFilterOptions',
            'listFilters',
            'salesChannelCriteria',
            'languageCriteria',
            'customerCriteria',
            'productCriteria',
            'columns',
            'repository',
            'criteria',
            'dateFilter',
        ],
        methods: [
            'createdComponent',
            'getList',
            'updateCriteria',
            'onDelete',
        ],
    },
});
