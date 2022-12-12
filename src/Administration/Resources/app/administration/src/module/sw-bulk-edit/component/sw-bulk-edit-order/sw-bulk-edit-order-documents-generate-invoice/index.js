/**
 * @package system-settings
 */
import template from './sw-bulk-edit-order-documents-generate-invoice.html.twig';
import './sw-bulk-edit-order-documents-generate-invoice.scss';

const { State } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    computed: {
        generateData: {
            get() {
                return State.get('swBulkEdit')?.orderDocuments?.invoice?.value;
            },
            set(generateData) {
                State.commit('swBulkEdit/setOrderDocumentsValue', {
                    type: 'invoice',
                    value: generateData,
                });
            },
        },
    },
};
