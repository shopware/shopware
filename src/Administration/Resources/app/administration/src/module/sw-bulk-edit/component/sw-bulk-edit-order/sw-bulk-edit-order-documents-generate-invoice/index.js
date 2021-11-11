import template from './sw-bulk-edit-order-documents-generate-invoice.html.twig';
import './sw-bulk-edit-order-documents-generate-invoice.scss';

const { Component, State } = Shopware;

Component.register('sw-bulk-edit-order-documents-generate-invoice', {
    template,

    computed: {
        generateData: {
            get() {
                return State.get('swBulkEdit').orderDocuments.invoice;
            },
            set(generateData) {
                State.commit('swBulkEdit/setOrderDocuments', {
                    type: 'invoice',
                    payload: generateData,
                });
            },
        },
    },
});
