/**
 * @sw-package checkout
 */
import useSwBulkEditStore from 'shopware:stores/swBulkEdit';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    computed: {
        generateData: {
            get() {
                return useSwBulkEditStore()?.orderDocuments?.storno?.value;
            },
            set(generateData) {
                useSwBulkEditStore().setOrderDocumentsValue({
                    type: 'storno',
                    value: generateData,
                });
            },
        },

        documentTypeTechnicalName() {
            return 'storno';
        },
    },
};
