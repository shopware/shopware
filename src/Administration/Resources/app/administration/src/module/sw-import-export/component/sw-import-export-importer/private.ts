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
    name: 'sw-import-export-importer',
    load: () => import('./src/sw-import-export-importer'),
    public: {
        props: [
            'sourceEntity',
        ],
        emits: [
            'import-started',
        ],
        blocks: [
            'sw_import_export_importer',
            'sw_import_export_importer_product_variants_warning',
            'sw_import_export_importer_product_variants_warning_text',
            'sw_import_export_importer_upload_csv_label',
            'sw_import_export_importer_warning_block',
            'sw_import_export_importer_file_input',
            'sw_import_export_importer_select_profile_label',
            'sw_import_export_importer_profile_select',
            'sw_import_export_importer_profile_select_product_variants_switch',
            'sw_import_export_importer_modal',
            'sw_import_export_importer_modal_exporter',
            'sw_import_export_importer_modal_footer',
            'sw_import_export_importer_progress',
        ],
        computed: [
            'profileCriteria',
            'logRepository',
            'disableImporting',
            'showProductVariantsInfo',
            'logCriteria',
        ],
        methods: [
            'onProfileSelect',
            'onStartProcess',
            'onStartDryRunProcess',
            'handleProgress',
            'setImportModalProfile',
        ],
    },
});
