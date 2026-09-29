/**
 * @sw-package checkout
 */
import template from './sw-bulk-edit-order-documents-generate-delivery-note.html.twig';
import useSwBulkEditStore from 'shopware:stores/swBulkEdit';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    computed: {
        generateData: {
            get() {
                return useSwBulkEditStore().orderDocuments?.delivery_note?.value;
            },
            set(generateData) {
                useSwBulkEditStore().setOrderDocumentsValue({
                    type: 'delivery_note',
                    value: generateData,
                });
            },
        },

        documentTypeTechnicalName() {
            return 'delivery_note';
        },
    },
};
