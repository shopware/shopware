import template from './sw-order-send-document-modal.html.twig';
import './sw-order-send-document-modal.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-send-document-modal', {
    template,

    props: {
        document: {
            type: Object,
            required: true,
        },
        order: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            isLoading: false,
            mailTemplate: null,
            subject: '',
            recipient: '',
            content: '',
        };
    },

    computed: {
        mailTemplateCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('mailTemplateType');

            return criteria;
        },

        primaryActionDisabled() {
            return this.mailTemplate === null || this.subject.length <= 0 || this.recipient.length <= 0;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.recipient = this.order.orderCustomer.email;
        },

        onMailTemplateChange(mailTemplateId, mailTemplate) {
            this.subject = mailTemplate.subject;
            this.content = mailTemplate.contentPlain;
        },

        onSendDocument() {
            this.isLoading = true;

            // ToDo - NEXT-16681 Implement mail delivery
            console.warn('sw-order-send-document-modal: NEXT-16681 - Implement mail delivery');

            this.$emit('document-sent');
            this.isLoading = false;
        },
    },
});
