import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-order-select-document-type-modal',
    load: () => import('./src/sw-order-select-document-type-modal'),
    public: {
        props: [
            'order',
            'value',
        ],
        emits: [
            'modal-close',
            'update:value',
        ],
        blocks: [
            'sw_order_select_document_type_modal',
            'sw_order_select_document_type_modal_document_types',
            'sw_order_select_document_type_modal_footer_action_cancel',
            'sw_order_select_document_type_modal_footer_action_create',
        ],
        computed: [
            'creditItems',
            'documentRepository',
            'documentTypeRepository',
            'documentTypeCriteria',
            'documentCriteria',
            'filteredDocumentTypes',
        ],
        methods: [
            'createdComponent',
            'documentTypeAvailable',
            'addHelpTextToOption',
            'onRadioFieldChange',
            'onChangeShowZugferd',
        ],
    },
});
