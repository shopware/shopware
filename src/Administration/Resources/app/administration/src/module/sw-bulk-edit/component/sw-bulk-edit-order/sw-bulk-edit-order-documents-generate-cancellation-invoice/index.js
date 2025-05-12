/**
 * @sw-package checkout
 */
const { Store } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    computed: {
        generateData: {
            get() {
                return Store.get('swBulkEdit')?.orderDocuments?.storno?.value;
            },
            set(generateData) {
                Store.get('swBulkEdit').setOrderDocumentsValue({
                    type: 'storno',
                    value: generateData,
                });
            },
        },
    },
};
