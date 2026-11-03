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
    name: 'sw-import-export-edit-profile-field-indicators',
    load: () => import('./src/sw-import-export-edit-profile-field-indicators'),
    public: {
        props: [
            'profile',
        ],
        blocks: [
            'sw_import_export_edit_profile_indicators',
            'sw_import_export_edit_profile_indicators_headline',
            'sw_import_export_edit_profile_indicators_text',
            'sw_import_export_edit_profile_indicators_separator_field',
            'sw_import_export_edit_profile_indicators_enclosure_field',
        ],
        computed: [
            'supportedDelimiter',
            'supportedEnclosures',
        ],
    },
});
