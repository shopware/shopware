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
    name: 'sw-import-export-view-import',
    load: () => import('./src/sw-import-export-view-import'),
    public: {
        blocks: [
            'sw_import_export_view_import',
            'sw_import_export_view_import_importer',
            'sw_import_export_view_import_activity',
            'sw_import_export_view_import_activity_title',
            'sw_import_export_view_import_activity_grid',
        ],
        methods: [
            'reloadContent',
        ],
    },
});
