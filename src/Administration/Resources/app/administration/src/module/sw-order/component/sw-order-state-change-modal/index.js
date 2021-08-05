import template from './sw-order-state-change-modal.html.twig';
import './sw-order-state-change-modal.scss';

const { Component } = Shopware;

Component.register('sw-order-state-change-modal', {
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

        technicalName: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            showModal: false,
            userCanConfirm: false,
        };
    },

    computed: {
        modalTitle() {
            return this.$tc('sw-order.assignMailTemplateCard.cardTitle');
        },
    },

    methods: {
        onCancel() {
            this.$emit('page-leave');
        },

        onDocsConfirm(docIds, sendMail = true) {
            this.$emit('page-leave-confirm', docIds, sendMail);
        },
    },
});
