import template from './sw-order-state-change-modal-attach-documents.html.twig';
import './sw-order-state-change-modal-attach-documents.scss';

const { Component } = Shopware;

Component.register('sw-order-state-change-modal-attach-documents', {
    template,

    props: {
        order: {
            type: Object,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            sendMail: true,
        };
    },

    methods: {
        onConfirm() {
            const docIds = [];
            this.$refs.attachDocuments.documents.forEach((doc) => {
                if (doc.attach) {
                    docIds.push(doc.id);
                }
            });

            this.$emit('on-confirm', docIds, this.sendMail);
        },
    },
});
