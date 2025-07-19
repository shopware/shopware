/**
 * @sw-package checkout
 */
import template from './sw-bulk-edit-order-documents-generate-delivery-note.html.twig';

const { Store } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    computed: {
        generateData: {
            get() {
                return Store.get('swBulkEdit').orderDocuments?.delivery_note?.value;
            },
            set(generateData) {
                Store.get('swBulkEdit').setOrderDocumentsValue({
                    type: 'delivery_note',
                    value: generateData,
                });
            },
        },
    },
};
