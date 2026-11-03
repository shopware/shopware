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
    name: 'sw-newsletter-recipient-list',
    load: () => import('./src/sw-newsletter-recipient-list'),
    public: {
        blocks: [
            'sw_newsletter_recipient_list',
            'sw_newsletter_recipient_list_search_bar',
            'sw_newsletter_recipient_list_smart_bar_header',
            'sw_newsletter_recipient_list_smart_bar_header_title',
            'sw_newsletter_recipientlist_list_smart_bar_header_title_text',
            'sw_newsletter_recipient_list_smart_bar_header_amount',
            'sw_newsletter_recipientlist_list_content',
            'sw_newsletter_recipient_list_empty_state',
            'sw_newsletter_recipientlist_list_content_card',
            'sw_newsletter_recipientlist_list_grid',
            'sw_newsletter_recipientlist_list_grid_email',
            'sw_newsletter_recipientlist_list_grid_name',
            'sw_newsletter_recipientlist_list_grid_inline_edit_name',
            'sw_newsletter_recipientlist_list_grid_inline_edit_first_name',
            'sw_newsletter_recipientlist_list_grid_inline_edit_last_name',
            'sw_newsletter_recipientlist_list_grid_name_link',
            'sw_newsletter_recipientlist_list_grid_status',
            'sw_newsletter_recipientlist_list_grid_status_notSet',
            'sw_newsletter_recipientlist_list_grid_status_optIn',
            'sw_newsletter_recipientlist_list_grid_status_optOut',
            'sw_newsletter_recipientlist_list_grid_status_direct',
            'sw_newsletter_recipientlist_list_grid_updated_at',
            'sw_newsletter_recipientlist_list_grid_created_at',
            'sw_newsletter_recipient_list_sidebar',
            'sw_newsletter_recipient_list_sidebar_refresh',
            'sw_newsletter_recipient_list_sidebar_filter',
            'sw_newsletter_recipient_list_sidebar_filter_status',
            'sw_newsletter_recipient_list_sidebar_filter_status_not_set',
            'sw_newsletter_recipient_list_sidebar_filter_status_direct',
            'sw_newsletter_recipient_list_sidebar_filter_status_opt_in',
            'sw_newsletter_recipient_list_sidebar_filter_status_opt_out',
            'sw_newsletter_recipient_list_sidebar_filter_language',
            'sw_newsletter_recipient_list_sidebar_filter_language_items',
            'sw_newsletter_recipient_list_sidebar_filter_sales_channel',
            'sw_newsletter_recipient_list_sidebar_filter_sales_channel_items',
            'sw_newsletter_recipient_list_sidebar_filter_sales_tags',
            'sw_newsletter_recipient_list_sidebar_filter_sales_tags_select',
        ],
        computed: [
            'columns',
            'salesChannelRepository',
            'newsletterRecipientRepository',
            'tagRepository',
            'dateFilter',
            'emailIdnFilter',
            'statusData',
            'adminEsEnable',
        ],
        methods: [
            'createdComponent',
            'getList',
            'onStatusSelectionChanged',
            'onLanguageSelectionChanged',
            'onSalesChannelSelectionChanged',
            'onTagSelectionChanged',
            'registerFilterSidebarItem',
            'openFilterSidebar',
            'getColumns',
            'handleTagFilter',
            'handleBooleanFilter',
            'onChange',
        ],
    },
});
