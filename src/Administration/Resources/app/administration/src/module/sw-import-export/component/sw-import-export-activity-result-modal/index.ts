import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-import-export-activity-result-modal',
    load: () => import('./src/sw-import-export-activity-result-modal'),
    public: {
        props: [
            'logEntity',
        ],
        emits: [
            'result-close',
        ],
        blocks: [
            'sw_import_export_activity_result_modal',
            'sw_import_export_activity_result_modal_info',
            'sw_import_export_activity_result_modal_info_mainEntity',
            'sw_import_export_activity_result_modal_info_mainEntity_list',
            'sw_import_export_activity_result_modal_info_mainEntity_list_insert',
            'sw_import_export_activity_result_modal_info_mainEntity_list_update',
            'sw_import_export_activity_result_modal_info_mainEntity_list_insert_error',
            'sw_import_export_activity_result_modal_info_mainEntity_list_update_error',
            'sw_import_export_activity_result_modal_info_mainEntity_list_insert_skip',
            'sw_import_export_activity_result_modal_info_mainEntity_list_update_skip',
            'sw_import_export_activity_result_modal_info_mainEntity_list_other_error',
            'sw_import_export_activity_result_modal_info_log',
            'sw_import_export_activity_result_modal_info_log_list',
            'sw_import_export_activity_result_modal_info_log_list_file_name',
            'sw_import_export_activity_result_modal_info_log_list_profile',
            'sw_import_export_activity_result_modal_info_log_list_imported',
            'sw_import_export_activity_result_modal_info_log_list_file_size',
            'sw_import_export_activity_result_modal_info_log_list_date',
            'sw_import_export_activity_result_modal_info_log_user',
            'sw_import_export_activity_result_modal_info_log_list_state',
            'sw_import_export_activity_result_modal_info_log_list_type',
            'sw_import_export_activity_result_modal_activity',
            'sw_import_export_activity_result_modal_activity_headline',
            'sw_import_export_activity_result_modal_activity_description',
            'sw_import_export_activity_result_modal_activity_table',
            'sw_import_export_activity_result_modal_activity_table_columns',
            'sw_import_export_activity_result_modal_activity_table_columns_label',
            'sw_import_export_activity_result_modal_activity_table_columns_changes',
            'sw_import_export_activity_result_modal_activity_table_columns_errors',
            'sw_import_export_activity_result_modal_activity_table_columns_skipped',
            'sw_import_export_activity_result_modal_download',
            'sw_import_export_activity_result_modal_download_records_button',
            'sw_import_export_activity_result_modal_download_invalid_records_button',
        ],
        computed: [
            'mainEntity',
            'mainEntityResult',
            'result',
            'logTypeText',
            'dateFilter',
        ],
        methods: [
            'calculateFileSize',
            'openDownload',
            'getStateLabel',
        ],
    },
});
