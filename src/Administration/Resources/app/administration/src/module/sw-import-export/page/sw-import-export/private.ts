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
    name: 'sw-import-export',
    load: () => import('./src/sw-import-export'),
    public: {
        blocks: [
            'sw_import_export',
            'sw_import_export_tabs',
            'sw_import_export_mt_tabs',
            'sw_import_export_tabs_import',
            'sw_import_export_tabs_export',
            'sw_import_export_tabs_profiles',
            'sw_import_export_language_switch',
        ],
        computed: [
            'importExportTabs',
        ],
    },
});
