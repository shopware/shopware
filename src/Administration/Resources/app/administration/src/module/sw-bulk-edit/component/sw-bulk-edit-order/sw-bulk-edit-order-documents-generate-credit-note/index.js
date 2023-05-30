/**
 * @package system-settings
 */
const { State } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    computed: {
        generateData: {
            get() {
                return State.get('swBulkEdit')?.orderDocuments?.credit_note?.value;
            },
            set(generateData) {
                State.commit('swBulkEdit/setOrderDocumentsValue', {
                    type: 'credit_note',
                    value: generateData,
                });
            },
        },
    },
};
