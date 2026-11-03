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
    name: 'sw-import-export-exporter',
    load: () => import('./src/sw-import-export-exporter'),
    public: {
        props: [
            'sourceEntity',
        ],
        emits: [
            'export-started',
        ],
        blocks: [
            'sw_import_export_exporter',
            'sw_import_export_exporter_header',
            'sw_import_export_exporter_product_variants_warning',
            'sw_import_export_exporter_product_variants_warning_text',
            'sw_import_export_exporter_profile_select',
            'sw_import_export_exporter_profile_select_product_variants_switch',
            'sw_import_export_exporter_modal',
            'sw_import_export_exporter_modal_exporter',
            'sw_import_export_exporter_modal_footer',
            'sw_import_export_exporter_progress',
        ],
        computed: [
            'profileCriteria',
            'disableExporting',
            'showProductVariantsInfo',
        ],
        methods: [
            'onProfileSelect',
            'onStartProcess',
            'handleProgress',
            'setExportModalProfile',
        ],
    },
});
