import template from './sw-bulk-edit-order-documents-generate-invoice.html.twig';
import './sw-bulk-edit-order-documents-generate-invoice.scss';

const { Component, State } = Shopware;

Component.register('sw-bulk-edit-order-documents-generate-invoice', {
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
});
