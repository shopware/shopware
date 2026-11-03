import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-cms-layout-modal',
    load: () => import('./src/sw-cms-layout-modal'),
    public: {
        props: [
            'headline',
            'cmsPageTypes',
            'preSelection',
        ],
        emits: [
            'modal-layout-select',
            'modal-close',
        ],
        blocks: [
            'sw_cms_layout_modal',
            'sw_cms_layout_modal_header',
            'sw_cms_layout_modal_header_search',
            'sw_cms_layout_modal_header_sorting_container',
            'sw_cms_layout_modal_header_sorting_select',
            'sw_cms_layout_modal_header_view_toggle',
            'sw_cms_layout_modal_header_view_toogle_grid',
            'sw_cms_layout_modal_header_view_toogle_list',
            'sw_cms_layout_modal_content',
            'sw_cms_layout_modal_loader',
            'sw_cms_layout_modal_content_container',
            'sw_cms_layout_modal_content_listing',
            'sw_cms_layout_modal_content_listing_item',
            'sw_cms_layout_modal_content_listing_item_checkbox',
            'sw_cms_layout_modal_content_listing_item_inner',
            'sw_cms_layout_modal_content_pagination',
            'sw_cms_list_listing_list_data_grid_column_name',
            'sw_cms_list_listing_list_data_grid_column_type',
            'sw_cms_list_listing_list_data_grid_column_created',
            'sw_cms_list_listing_list_data_grid_column_updated',
            'sw_cms_list_listing_list_data_grid_pagination',
            'sw_cms_layout_modal_footer',
        ],
        computed: [
            'pageRepository',
            'cmsPageCriteria',
            'columnConfig',
            'gridPreSelection',
            'dateFilter',
        ],
        methods: [
            'createdComponent',
            'getList',
            'selectLayout',
            'selectInGrid',
            'selectItem',
            'onSearch',
            'toggleListMode',
            'gridItemClasses',
            'closeModal',
            'getPageType',
            'getDefaultLayouts',
        ],
    },
});
