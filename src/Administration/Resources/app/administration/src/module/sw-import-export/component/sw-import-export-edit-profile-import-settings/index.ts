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
    name: 'sw-import-export-edit-profile-import-settings',
    load: () => import('./src/sw-import-export-edit-profile-import-settings'),
    public: {
        props: [
            'profile',
        ],
        blocks: [
            'sw_import_export_edit_profile_import_settings',
            'sw_import_export_edit_profile_import_settings_headline',
            'sw_import_export_edit_profile_import_settings_create_switch',
            'sw_import_export_edit_profile_import_settings_update_switch',
        ],
        methods: [
            'onChange',
            'handleCreateEntities',
            'handleUpdateEntities',
        ],
    },
});
