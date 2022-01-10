const { Component, State } = Shopware;

Component.extend('sw-bulk-edit-order-documents-generate-credit-note', 'sw-bulk-edit-order-documents-generate-invoice', {
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
});
