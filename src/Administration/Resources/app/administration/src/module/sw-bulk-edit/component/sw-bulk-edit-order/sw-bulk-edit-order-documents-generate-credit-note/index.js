/**
 * @sw-package checkout
 */
const { Store } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    computed: {
        generateData: {
            get() {
                return Store.get('swBulkEdit')?.orderDocuments?.credit_note?.value;
            },
            set(generateData) {
                Store.get('swBulkEdit').setOrderDocumentsValue({
                    type: 'credit_note',
                    value: generateData,
                });
            },
        },
    },
};
