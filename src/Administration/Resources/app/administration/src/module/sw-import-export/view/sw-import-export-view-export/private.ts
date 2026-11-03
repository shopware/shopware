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
    name: 'sw-import-export-view-export',
    load: () => import('./src/sw-import-export-view-export'),
    public: {
        blocks: [
            'sw_import_export_view_export',
            'sw_import_export_view_export_exporter',
            'sw_import_export_view_export_activity',
            'sw_import_export_view_export_activity_title',
            'sw_import_export_view_export_activity_grid',
        ],
        methods: [
            'reloadContent',
        ],
    },
});
