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
    name: 'sw-import-export-new-profile-wizard-csv-page',
    load: () => import('./src/sw-import-export-new-profile-wizard-csv-page'),
    public: {
        props: [
            'profile',
        ],
        emits: [
            'next-disable',
            'next-allow',
        ],
        blocks: [
            'sw_import_export_new_profile_wizard_csv_page',
            'sw_import_export_new_profile_wizard_csv_page_text',
            'sw_import_export_new_profile_wizard_csv_page_csv_upload',
        ],
        methods: [
            'createdComponent',
            'onFileChange',
            'transformMapping',
        ],
    },
});
