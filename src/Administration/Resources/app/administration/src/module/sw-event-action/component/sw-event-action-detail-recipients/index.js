import template from './sw-event-action-detail-recipients.html.twig';
import './sw-event-action-detail-recipients.scss';

const { Component, Utils, Classes: { ShopwareError } } = Shopware;

Component.register('sw-event-action-detail-recipients', {
    template,

    inject: ['acl'],

    props: {
        configRecipients: {
            type: Object,
            required: false,
            default: null,
        },
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            recipients: [],
        };
    },

    computed: {
        recipientColumns() {
            return [{
                property: 'email',
                label: 'sw-event-action.detail.columnRecipientMail',
                inlineEdit: 'string',
            }, {
                property: 'name',
                label: 'sw-event-action.detail.columnRecipientName',
                inlineEdit: 'string',
            }];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getRecipientList();
        },

        getRecipientList() {
            if (!this.configRecipients) {
                return;
            }

            // Convert recipients object from config to array to work properly with `sw-data-grid`
            this.recipients = Object.entries(this.configRecipients).map((item) => {
                return {
                    email: item[0],
                    name: item[1],
                    id: Utils.createId(),
                };
            });
        },

        addRecipient() {
            const newId = Utils.createId();

            this.recipients.unshift({
                id: newId,
                email: '',
                name: '',
            });

            this.$nextTick(() => {
                this.$refs.recipientsGrid.currentInlineEditId = newId;
                this.$refs.recipientsGrid.enableInlineEdit();
            });
        },

        saveRecipient(recipient) {
            // If required fields are not filled, re-enable inline-edit
            if (!recipient.name.length || !recipient.email.length) {
                this.$nextTick(() => {
                    this.$refs.recipientsGrid.currentInlineEditId = recipient.id;
                    this.$refs.recipientsGrid.enableInlineEdit();
                });
                return;
            }

            this.$emit('update-list', this.recipients);
        },

        cancelSaveRecipient(recipient) {
            if (recipient.name.length || recipient.email.length) {
                return;
            }

            const index = this.recipients.findIndex((item) => {
                return item.id === recipient.id;
            });

            this.recipients.splice(index, 1);
        },

        onEditRecipient(id) {
            this.$refs.recipientsGrid.currentInlineEditId = id;
            this.$refs.recipientsGrid.enableInlineEdit();
        },

        onDeleteRecipient(id) {
            const index = this.recipients.findIndex((item) => {
                return item.id === id;
            });

            this.recipients.splice(index, 1);

            this.$emit('update-list', this.recipients);
        },

        recipientMailError(text) {
            if (text.length) {
                return null;
            }

            return new ShopwareError({
                code: 'EVENT_ACTION_DETAIL_RECIPIENT_INVALID_MAIL',
            });
        },

        recipientNameError(text) {
            if (text.length) {
                return null;
            }

            return new ShopwareError({
                code: 'EVENT_ACTION_DETAIL_RECIPIENT_INVALID_NAME',
            });
        },
    },
});
