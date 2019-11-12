import template from './sw-order-state-change-modal.html.twig';

const { Component } = Shopware;

Component.register('sw-order-state-change-modal', {
    template,

    inject: [],

    props: {
        order: {
            type: Object,
            required: true
        },
        isLoading: {
            type: Boolean,
            required: true
        }
    },

    data() {
        return {
            showModal: false
        };
    },

    computed: {
    },

    created() {

    },

    methods: {
        onConfirm() {
            const docIds = [];
            this.$refs.attachDocuments.documents.forEach((doc) => {
                if (doc.attach) {
                    docIds.push(doc.id);
                }
            });
            this.$emit('page-leave-confirm', docIds);
        },

        onCancel() {
            this.$emit('page-leave');
        }

    }
});
