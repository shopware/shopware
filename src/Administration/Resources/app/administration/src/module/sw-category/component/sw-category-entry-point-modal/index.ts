import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-category-entry-point-modal',
    load: () => import('./src/sw-category-entry-point-modal'),
    public: {
        props: [
            'salesChannelCollection',
        ],
        emits: [
            'modal-close',
        ],
        blocks: [
            'sw_category_entry_point_modal',
            'sw_category_entry_point_modal_sales_channel_selection',
            'sw_category_entry_point_modal_selected_sales_channel',
            'sw_category_entry_point_modal_selected_sales_channel_show_in_main_navigation',
            'sw_category_entry_point_modal_selected_sales_channel_name_in_main_navigation',
            'sw_category_entry_point_modal_selected_sales_channel_layout_headline',
            'sw_category_entry_point_modal_selected_sales_channel_layout_selection',
            'sw_category_entry_point_modal_layout_preview',
            'sw_category_entry_point_modal_layout_modal',
            'sw_category_entry_point_modal_layout_desc',
            'sw_category_entry_point_modal_layout_desc_info',
            'sw_category_entry_point_modal_layout_desc_info_headline',
            'sw_category_entry_point_modal_layout_desc_info_subheadline',
            'sw_category_entry_point_modal_layout_desc_actions',
            'sw_category_entry_point_modal_layout_desc_actions_layout',
            'sw_category_entry_point_modal_layout_desc_actions_designer',
            'sw_category_entry_point_modal_layout_desc_actions_remove',
            'sw_category_entry_point_modal_selected_sales_channel_seo_settings_headline',
            'sw_category_entry_point_modal_selected_sales_channel_seo_meta_title',
            'sw_category_entry_point_modal_selected_sales_channel_seo_meta_description',
            'sw_category_entry_point_modal_selected_sales_channel_seo_keywords',
            'sw_category_entry_point_modal_footer',
            'sw_category_entry_point_modal_footer_cancel_button',
            'sw_category_entry_point_modal_footer_apply_button',
            'sw_category_entry_point_modal_discard_changes_modal',
        ],
        computed: [
            'selectedSalesChannel',
        ],
        methods: [
            'createdComponent',
            'closeModal',
            'getCmsPageTypeName',
            'onLayoutSelect',
            'onLayoutReset',
            'openInPagebuilder',
            'openLayoutModal',
            'closeLayoutModal',
            'applyChanges',
            'hasNotAppliedChanges',
            'isAttributeEqual',
            'onLeaveModalClose',
            'onLeaveModalConfirm',
        ],
    },
});
