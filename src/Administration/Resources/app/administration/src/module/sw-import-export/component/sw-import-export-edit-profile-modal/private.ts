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
    name: 'sw-import-export-edit-profile-modal',
    load: () => import('./src/sw-import-export-edit-profile-modal'),
    public: {
        props: [
            'profile',
            'show',
        ],
        emits: [
            'profile-close',
            'profile-save',
        ],
        blocks: [
            'sw_import_export_edit_profile_modal',
            'sw_import_export_edit_profile_modal_alert',
            'sw_import_export_edit_profile_modal_tabs',
            'sw_import_export_edit_profile_modal_mt_tabs',
            'sw_import_export_edit_profile_modal_mt_tabs_content',
            'sw_import_export_edit_profile_modal_mt_tabs_general_import_settings',
            'sw_import_export_edit_profile_modal_mt_tabs_mappings',
            'sw_import_export_edit_profile_modal_mt_tabs_mappings_text',
            'sw_import_export_edit_profile_modal_mt_tabs_mappings_mapping',
            'sw_import_export_edit_profile_modal_mt_tabs_advanced',
            'sw_import_export_edit_profile_modal_mt_tabs_advanced_text',
            'sw_import_export_edit_profile_modal_mt_tabs_advanced_identifiers',
            'sw_import_export_edit_profile_modal_tabs_general',
            'sw_import_export_edit_profile_modal_tabs_field_mappings',
            'sw_import_export_edit_profile_modal_tabs_field_advanced',
            'sw_import_export_edit_profile_modal_tabs_general_import_settings',
            'sw_import_export_edit_profile_modal_tabs_mappings',
            'sw_import_export_edit_profile_modal_tabs_mappings_text',
            'sw_import_export_edit_profile_modal_tabs_mappings_mapping',
            'sw_import_export_edit_profile_modal_tabs_advanced',
            'sw_import_export_edit_profile_modal_tabs_advanced_text',
            'sw_import_export_edit_profile_modal_tabs_advanced_identifiers',
            'sw_import_export_edit_profile_modal_footer',
            'sw_import_export_edit_profile_violation_modal',
            'sw_import_export_edit_profile_violation_modal_message',
            'sw_import_export_edit_profile_violation_modal_required_fields',
            'sw_import_export_edit_profile_violation_modal_required_field',
            'sw_import_export_edit_profile_violation_modal_footer',
        ],
        computed: [
            'isNew',
            'modalTitle',
            'saveLabelSnippet',
            'showValidationError',
            'profileRepository',
            'profileTabs',
        ],
        methods: [
            'saveProfile',
            'updateMapping',
            'getParentProfileSelected',
            'checkValidation',
            'resetViolations',
            'loadSystemRequiredFieldsForEntity',
        ],
    },
});
