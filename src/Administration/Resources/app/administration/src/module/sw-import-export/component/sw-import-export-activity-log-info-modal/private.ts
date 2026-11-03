import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-import-export-activity-log-info-modal',
    load: () => import('./src/sw-import-export-activity-log-info-modal'),
    public: {
        props: [
            'logEntity',
        ],
        emits: [
            'log-close',
        ],
        blocks: [
            'sw_import_export_activity_log_info_modal',
            'sw_import_export_activity_log_info_modal_description',
            'sw_import_export_activity_log_info_modal_description_name',
            'sw_import_export_activity_log_info_modal_description_profile',
            'sw_import_export_activity_log_info_modal_description_records',
            'sw_import_export_activity_log_info_modal_description_size',
            'sw_import_export_activity_log_info_modal_description_date',
            'sw_import_export_activity_log_info_modal_description_user',
            'sw_import_export_activity_log_info_modal_description_state',
            'sw_import_export_activity_log_info_modal_description_type',
            'sw_import_export_activity_log_info_modal_download',
        ],
        computed: [
            'typeText',
            'stateClass',
            'dateFilter',
        ],
        methods: [
            'calculateFileSize',
            'openDownload',
            'getStateLabel',
        ],
    },
});
