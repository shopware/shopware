/**
 * @sw-package checkout
 */
import useSwBulkEditStore from 'shopware:stores/swBulkEdit';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    computed: {
        generateData: {
            get() {
                return useSwBulkEditStore()?.orderDocuments?.credit_note?.value;
            },
            set(generateData) {
                useSwBulkEditStore().setOrderDocumentsValue({
                    type: 'credit_note',
                    value: generateData,
                });
            },
        },

        documentTypeTechnicalName() {
            return 'credit_note';
        },
    },
};
