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
    name: 'sw-import-export-new-profile-wizard-general-page',
    load: () => import('./src/sw-import-export-new-profile-wizard-general-page'),
    public: {
        props: [
            'profile',
        ],
        emits: [
            'next-allow',
            'next-disable',
        ],
        blocks: [
            'sw_import_export_new_profile_wizard_general_page',
            'sw_import_export_new_profile_wizard_general_page_general',
            'sw_import_export_new_profile_wizard_general_page_indicators',
            'sw_import_export_new_profile_wizard_general_page_import_settings',
        ],
        computed: [
            'inputValid',
        ],
        methods: [
            'isFieldFilled',
        ],
    },
});
