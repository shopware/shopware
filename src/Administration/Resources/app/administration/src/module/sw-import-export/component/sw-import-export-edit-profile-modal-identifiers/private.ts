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
    name: 'sw-import-export-edit-profile-modal-identifiers',
    load: () => import('./src/sw-import-export-edit-profile-modal-identifiers'),
    public: {
        props: [
            'profile',
        ],
        blocks: [
            'sw_import_export_edit_profile_modal_identifiers',
            'sw_import_export_edit_profile_modal_identifiers_grid',
            'sw_import_export_edit_profile_modal_identifiers_grid_entity_name_column',
            'sw_import_export_edit_profile_modal_identifiers_grid_mapped_column',
            'sw_import_export_edit_profile_modal_identifiers_empty_state',
        ],
        computed: [
            'languageRepository',
            'currencyRepository',
            'customFieldSetRepository',
            'languageCriteria',
            'currencyCriteria',
            'customFieldSetCriteria',
            'identifierColumns',
            'identifiers',
        ],
        methods: [
            'createdComponent',
            'onChangeIdentifier',
        ],
    },
});
