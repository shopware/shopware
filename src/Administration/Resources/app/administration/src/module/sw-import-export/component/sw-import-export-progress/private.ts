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
    name: 'sw-import-export-progress',
    load: () => import('./src/sw-import-export-progress'),
    public: {
        props: [
            'activityType',
            'disableButton',
        ],
        emits: [
            'process-start',
            'process-start-dryrun',
        ],
        blocks: [
            'sw_import_export_progress',
            'sw_import_export_progress_start_process',
            'sw_import_export_progress_start_process_dryrun',
        ],
    },
});
