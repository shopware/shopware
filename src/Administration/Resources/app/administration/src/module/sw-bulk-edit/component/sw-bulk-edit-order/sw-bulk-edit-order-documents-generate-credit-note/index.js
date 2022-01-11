const { Component, State } = Shopware;

Component.extend('sw-bulk-edit-order-documents-generate-credit-note', 'sw-bulk-edit-order-documents-generate-invoice', {
    computed: {
        generateData: {
            get() {
                return State.get('swBulkEdit').orderDocuments.credit_note;
            },
            set(generateData) {
                State.commit('swBulkEdit/setOrderDocuments', {
                    type: 'credit_note',
                    payload: generateData,
                });
            },
        },
    },
});
