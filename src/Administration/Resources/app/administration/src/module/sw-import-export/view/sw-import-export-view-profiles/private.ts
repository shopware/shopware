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
    name: 'sw-import-export-view-profiles',
    load: () => import('./src/sw-import-export-view-profiles'),
    public: {
        blocks: [
            'sw_import_export_view_profile',
            'sw_import_export_view_profile_profiles',
            'sw_import_export_view_profile_profiles_toolbar',
            'sw_import_export_view_profile_profiles_toolbar_search_field',
            'sw_import_export_view_profile_profiles_toolbar_add_new_profile',
            'sw_import_export_view_profile_profiles_listing',
            'sw_import_export_view_profile_profiles_listing_column_label',
            'sw_import_export_view_profile_profiles_listing_column_system_default',
            'sw_import_export_view_profile_profiles_listing_edit',
            'sw_import_export_view_profile_profiles_listing_duplicate',
            'sw_import_export_view_profile_profiles_listing_download_template',
            'sw_import_export_view_profile_profiles_listing_delete',
            'sw_import_export_view_profile_modal',
            'sw_import_export_view_new_profile_wizard',
        ],
        computed: [
            'profileRepository',
            'profileCriteria',
            'profilesColumns',
        ],
        methods: [
            'createdComponent',
            'loadProfiles',
            'reloadContent',
            'onSearch',
            'onAddNewProfile',
            'onEditProfile',
            'onDuplicateProfile',
            'onDownloadTemplate',
            'onDeleteProfile',
            'closeSelectedProfile',
            'saveSelectedProfile',
            'onError',
            'getTypeLabel',
            'onCloseNewProfileWizard',
        ],
    },
});
