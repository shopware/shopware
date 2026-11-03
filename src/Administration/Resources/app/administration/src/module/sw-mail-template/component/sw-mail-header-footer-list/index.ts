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
    name: 'sw-mail-header-footer-list',
    load: () => import('./src/sw-mail-header-footer-list'),
    public: {
        props: [
            'searchTerm',
        ],
        blocks: [
            'sw_mail_header_footer_list_grid',
            'sw_mail_header_footer_list_grid_empty_state',
            'sw_mail_header_footer_list_grid_empty_state_button',
            'sw_mail_header_footer_list_grid_empty_state_icon',
            'sw_mail_template_list_grid',
            'sw_mail_template_list_columns_sales_channels_label',
            'sw_mail_header_footer_list_grid_columns_actions_duplicate',
        ],
        computed: [
            'mailHeaderFooterRepository',
            'skeletonItemAmount',
            'showListing',
            'assetFilter',
        ],
        methods: [
            'onEdit',
            'getList',
            'getListColumns',
            'getSalesChannelsString',
            'onDuplicate',
            'checkCanBeDeleted',
            'onDelete',
            'getMailHeaderFooterCriteria',
            'onMultipleDelete',
            'showDeleteErrorNotification',
            'updateRecords',
        ],
    },
});
