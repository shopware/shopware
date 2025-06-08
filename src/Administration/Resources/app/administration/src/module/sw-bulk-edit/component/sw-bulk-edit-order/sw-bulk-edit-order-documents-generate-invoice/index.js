/**
 * @sw-package checkout
 */
import template from './sw-bulk-edit-order-documents-generate-invoice.html.twig';
import './sw-bulk-edit-order-documents-generate-invoice.scss';

const { Store } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['feature'],

    computed: {
        generateData: {
            get() {
                return Store.get('swBulkEdit')?.orderDocuments?.invoice?.value;
            },
            set(generateData) {
                Store.get('swBulkEdit').setOrderDocumentsValue({
                    type: 'invoice',
                    value: generateData,
                });
            },
        },
    },
};
