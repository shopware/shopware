import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * Lists the import or export processes of the merchant with their state, files and profiles, and refreshes the running ones.
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-import-export-activity',
    load: () => import('./src/sw-import-export-activity'),
    public: {
        props: [
            'type',
        ],
        blocks: [
            'sw_import_export_activity',
            'sw_import_export_activity_listing',
            'sw_import_export_activity_listing_size',
            'sw_import_export_activity_listing_records',
            'sw_import_export_activity_listing_invalid_records',
            'sw_import_export_activity_listing_createdAt',
            'sw_import_export_activity_listing_state',
            'sw_import_export_activity_listing_actions',
            'sw_import_export_activity_listing_actions_show_results',
            'sw_import_export_activity_listing_actions_show_log_info',
            'sw_import_export_activity_listing_actions_download_file',
            'sw_import_export_activity_listing_actions_open_profile',
            'sw_import_export_activity_listing_actions_abort_process',
            'sw_import_export_activity_listing_actions_download_invalid',
            'sw_import_export_activity_spinner',
            'sw_import_export_activity_empty_state',
            'sw_import_export_activity_log_info_modal',
            'sw_import_export_activity_result_modal',
            'sw_import_export_activity_modal',
        ],
        computed: [
            'logRepository',
            'profileRepository',
            'activityCriteria',
            'exportActivityColumns',
            'hasActivitiesInProgress',
            'downloadFileText',
            'showGrid',
            'showEmptyState',
            'showSpinner',
            'emptyStateSubLine',
            'emptyStateTitle',
            'dateFilter',
        ],
        methods: [
            'createdComponent',
            'addActivity',
            'fetchActivities',
            'updateActivitiesInProgress',
            'updateActivitiesFromLogs',
            'onOpenProfile',
            'onAbortProcess',
            'closeSelectedProfile',
            'onShowLog',
            'onShowResult',
            'closeSelectedLog',
            'closeSelectedResult',
            'openProcessFileDownload',
            'saveSelectedProfile',
            'calculateFileSize',
            'getStateLabel',
            'getStateClass',
        ],
    },
});
