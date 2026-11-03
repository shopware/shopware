/**
 * @sw-package checkout
 */
import template from './sw-bulk-edit-order-documents-generate-delivery-note.html.twig';
import { useBulkEditStore } from '../dependencies';

/**
 * @public
 */
export default {
    template,

    computed: {
        generateData: {
            get() {
                return useBulkEditStore().orderDocuments?.delivery_note?.value;
            },
            set(generateData) {
                useBulkEditStore().setOrderDocumentsValue({
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
