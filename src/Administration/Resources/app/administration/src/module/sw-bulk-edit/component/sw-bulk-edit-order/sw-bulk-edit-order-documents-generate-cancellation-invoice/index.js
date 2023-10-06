/**
 * @package system-settings
 */
const { State } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    computed: {
        generateData: {
            get() {
                return State.get('swBulkEdit')?.orderDocuments?.storno?.value;
            },
            set(generateData) {
                State.commit('swBulkEdit/setOrderDocumentsValue', {
                    type: 'storno',
                    value: generateData,
                });
            },
        },
    },
};
