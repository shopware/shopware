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
    name: 'sw-import-export-edit-profile-general',
    load: () => import('./src/sw-import-export-edit-profile-general'),
    public: {
        props: [
            'profile',
        ],
        blocks: [
            'sw_import_export_edit_profile_general',
            'sw_import_export_edit_profile_general_text',
            'sw_import_export_edit_profile_general_container',
            'sw_import_export_edit_profile_general_container_name',
            'sw_import_export_edit_profile_general_container_technical_name',
            'sw_import_export_edit_profile_general_container_object_type',
            'sw_import_export_edit_profile_general_container_object_type_select',
            'sw_import_export_edit_profile_general_container_object_type_select_result',
            'sw_import_export_edit_profile_general_container_object_type_select_result_highlight',
            'sw_import_export_edit_profile_general_container_object_type_select_result_text',
            'sw_import_export_edit_profile_general_container_type',
            'sw_import_export_edit_profile_general_container_type_result',
            'sw_import_export_edit_profile_general_container_type_result_highlight',
            'sw_import_export_edit_profile_general_container_type_result_text',
        ],
        computed: [
            'supportedProfileTypes',
            'supportedEntities',
            'mappingLength',
        ],
        methods: [
            'shouldDisableProfileType',
            'shouldDisableObjectType',
        ],
    },
});
