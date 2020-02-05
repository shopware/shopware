import template from './sw-order-state-change-modal.html.twig';
import './sw-order-state-change-modal.scss';

const { Component } = Shopware;

Component.register('sw-order-state-change-modal', {
    template,

    props: {
        order: {
            type: Object,
            required: true
        },

        isLoading: {
            type: Boolean,
            required: true
        },

        mailTemplatesExist: {
            required: false
        },

        technicalName: {
            type: String,
            required: true
        }
    },

    data() {
        return {
            showModal: false,
            assignMailTemplatesOptions: [],
            userCanConfirm: false,
            userHasAssignedMailTemplate: false
        };
    },

    computed: {
        modalTitle() {
            return this.mailTemplatesExist || this.userHasAssignedMailTemplate ?
                this.$tc('sw-order.documentCard.cardTitle') :
                this.$tc('sw-order.assignMailTemplateCard.cardTitle');
        },

        showDocuments() {
            return this.mailTemplatesExist || this.userHasAssignedMailTemplate;
        }
    },

    methods: {
        onCancel() {
            this.$emit('page-leave');
        },

        onDocsConfirm(docIds) {
            this.$emit('page-leave-confirm', docIds);
        },

        onNoMailConfirm() {
            this.$emit('page-leave-confirm', []);
        },

        onAssignMailTemplate() {
            this.userHasAssignedMailTemplate = true;
        }
    }
});
