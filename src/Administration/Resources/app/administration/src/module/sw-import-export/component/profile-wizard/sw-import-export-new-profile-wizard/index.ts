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
    name: 'sw-import-export-new-profile-wizard',
    load: () => import('./src/sw-import-export-new-profile-wizard'),
    public: {
        props: [
            'profile',
        ],
        emits: [
            'close',
            'profile-save',
        ],
        blocks: [
            'sw_import_export_new_profile_wizard',
            'sw_import_export_new_profile_wizard_page_general',
            'sw_import_export_new_profile_wizard_page_csv',
            'sw_import_export_new_profile_wizard_page_mapping',
            'sw_import_export_new_profile_wizard_footer_right_button',
            'sw_import_export_new_profile_wizard_footer_right_button_finish',
            'sw_import_export_new_profile_wizard_footer_right_button_skip',
            'sw_import_export_new_profile_wizard_footer_right_button_next',
            'sw_import_export_new_profile_wizard_violation_modal',
            'sw_import_export_new_profile_wizard_violation_modal_message',
            'sw_import_export_new_profile_wizard_violation_modal_required_fields',
            'sw_import_export_new_profile_wizard_violation_modal_required_field',
            'sw_import_export_new_profile_wizard_violation_modal_footer',
        ],
        computed: [
            'profileRepository',
            'showValidationError',
            'showNextButton',
            'showCsvSkipButton',
            'parentProfileCriteria',
        ],
        methods: [
            'onClose',
            'onFinish',
            'pageTitleSnippet',
            'onNextAllow',
            'onNextDisable',
            'loadSystemRequiredFieldsForEntity',
            'saveProfile',
            'getParentProfileSelected',
            'checkValidation',
            'resetViolations',
            'onCurrentPageChange',
            'onNextPage',
        ],
    },
});
