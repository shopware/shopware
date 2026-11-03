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
    name: 'sw-pagination',
    load: () => import('./src/sw-pagination'),
    public: {
        props: [
            'total',
            'limit',
            'page',
            'totalVisible',
            'steps',
            'autoHide',
        ],
        emits: [
            'page-change',
        ],
        blocks: [
            'sw_pagination',
            'sw_pagination_page_prev',
            'sw_pagination_page_list',
            'sw_pagination_page_list_item',
            'sw_pagination_page_list_item_button',
            'sw_pagination_page_list_item_separator',
            'sw_pagination_page_next',
            'sw_pagination_per_page_selection',
        ],
        computed: [
            'maxPage',
            'displayedPages',
            'shouldBeVisible',
            'possibleSteps',
            'possibleStepsOptions',
        ],
        methods: [
            'range',
            'pageChange',
            'onPageSizeChange',
            'firstPage',
            'prevPage',
            'nextPage',
            'lastPage',
            'changePageByPageNumber',
            'refresh',
        ],
    },
});
