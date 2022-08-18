const { Component, State } = Shopware;

// eslint-disable-next-line max-len
Component.extend('sw-bulk-edit-order-documents-generate-cancellation-invoice', 'sw-bulk-edit-order-documents-generate-invoice', {
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
});
