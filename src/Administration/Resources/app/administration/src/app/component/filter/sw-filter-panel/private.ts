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
    name: 'sw-filter-panel',
    load: () => import('./src/sw-filter-panel'),
    public: {
        props: [
            'filters',
            'defaults',
            'storeKey',
        ],
        emits: [
            'criteria-changed',
        ],
        blocks: [
            'sw_filter_panel',
            'sw_filter_panel_item',
            'sw_filter_panel_extension_point',
            'sw_multi_select_filter_content_path_label',
            'sw_multi_select_filter_content_path_result_item',
            'sw_multi_select_filter_content_path_result_label',
        ],
        computed: [
            'criteria',
            'isFilterActive',
            'activeFiltersNumber',
            'listFilters',
        ],
        methods: [
            'createdComponent',
            'updateFilter',
            'resetFilter',
            'resetAll',
            'showFilter',
            'getBreadcrumb',
            'getLabelName',
        ],
    },
});
