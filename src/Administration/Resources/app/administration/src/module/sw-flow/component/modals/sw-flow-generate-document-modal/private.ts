import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-flow-generate-document-modal',
    load: () => import('./src/sw-flow-generate-document-modal'),
    public: {
        props: [
            'sequence',
        ],
        emits: [
            'modal-close',
            'process-finish',
        ],
        blocks: [
            'sw_flow_generate_document_modal',
            'sw_flow_generate_document_modal_document_type_multiple',
            'sw_flow_generate_document_modal_document_type_single',
            'sw_flow_generate_document_modal_file_formats',
            'sw_flow_generate_document_modal_footer',
            'sw_flow_generate_document_modal_footer_cancel_button',
            'sw_flow_generate_document_modal_footer_save_button',
        ],
        computed: [
            'documentTypeRepository',
            'documentTypeCriteria',
            'isDocumentGenerationReworkActive',
            'documentTypeOptions',
            'fileFormatOptions',
        ],
        methods: [
            'createdComponent',
            'initializeLegacyState',
            'loadSupportedDocumentTypes',
            'onDocumentTypeSelectedChange',
            'onClose',
            'onAddAction',
            'onAddLegacyAction',
        ],
    },
});
