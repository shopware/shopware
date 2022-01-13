import template from './sw-bulk-edit-order-documents-generate-delivery-note.html.twig';

const { Component, State } = Shopware;

Component.extend('sw-bulk-edit-order-documents-generate-delivery-note', 'sw-bulk-edit-order-documents-generate-invoice', {
    template,

    computed: {
        generateData: {
            get() {
                return State.get('swBulkEdit').orderDocuments.delivery_note;
            },
            set(generateData) {
                State.commit('swBulkEdit/setOrderDocuments', {
                    type: 'delivery_note',
                    payload: generateData,
                });
            },
        },
    },
});
