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

        /**
         * @deprecated tag:v6.4.0 - Will be removed. Mail template assignment will be done via "sw-event-action".
         */
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
            /** @deprecated tag:v6.4.0 - Will be removed. Mail template assignment will be done via "sw-event-action". */
            userHasAssignedMailTemplate: false
        };
    },

    computed: {
        modalTitle() {
            return this.mailTemplatesExist || this.userHasAssignedMailTemplate ?
                this.$tc('sw-order.documentCard.modalTitle') :
                this.$tc('sw-order.assignMailTemplateCard.cardTitle');
        },

        /**
         * @deprecated tag:v6.4.0 - Will be removed. The check for mail templates is no longer needed.
         * Documents modal content will always be visible by default.
         * Mail template assignment will be done via "sw-event-action".
         */
        showDocuments() {
            return this.mailTemplatesExist || this.userHasAssignedMailTemplate;
        }
    },

    methods: {
        onCancel() {
            this.$emit('page-leave');
        },

        onDocsConfirm(docIds, sendMail = true) {
            this.$emit('page-leave-confirm', docIds, sendMail);
        },

        /**
         * @deprecated tag:v6.4.0 - Will be removed. Mail template assignment will be done via "sw-event-action".
         */
        onNoMailConfirm() {
            this.$emit('page-leave-confirm', []);
        },

        /**
         * @deprecated tag:v6.4.0 - Will be removed. Mail template assignment will be done via "sw-event-action".
         */
        onAssignMailTemplate() {
            this.userHasAssignedMailTemplate = true;
        }
    }
});
