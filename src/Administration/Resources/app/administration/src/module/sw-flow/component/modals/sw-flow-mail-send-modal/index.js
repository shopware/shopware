import { email as emailValidation } from 'src/core/service/validation.service';
import template from './sw-flow-mail-send-modal.html.twig';
import './sw-flow-mail-send-modal.scss';

const { Component, Utils, Classes: { ShopwareError } } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState } = Component.getComponentHelper();

Component.register('sw-flow-mail-send-modal', {
    template,

    inject: [
        'repositoryFactory',
    ],

    props: {
        sequence: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            showCreateMailTemplateModal: false,
            mailTemplateId: '',
            showRecipientEmails: false,
            mailRecipient: null,
            documentTypeIds: [],
            recipients: [],
            selectedRecipient: null,
            mailTemplateIdError: null,
            recipientGridError: null,
        };
    },

    computed: {
        mailTemplateCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('mailTemplateType');

            return criteria;
        },

        documentTypeRepository() {
            return this.repositoryFactory.create('document_type');
        },

        isNewMail() {
            return !this.sequence?.id;
        },

        recipientOptions() {
            return [
                {
                    value: 'default',
                    label: this.$tc('sw-flow.modals.mail.labelDefault'),
                },
                {
                    value: 'admin',
                    label: this.$tc('sw-flow.modals.mail.labelAdmin'),
                },
                {
                    value: 'custom',
                    label: this.$tc('sw-flow.modals.mail.labelCustom'),
                },
            ];
        },

        recipientColumns() {
            return [{
                property: 'email',
                label: 'sw-flow.modals.mail.columnRecipientMail',
                inlineEdit: 'string',
            }, {
                property: 'name',
                label: 'sw-flow.modals.mail.columnRecipientName',
                inlineEdit: 'string',
            }];
        },

        ...mapState('swFlowState', ['mailTemplates']),
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.mailRecipient = this.recipientOptions.find(recipient => recipient.value === 'default').value;

            if (!this.isNewMail) {
                const { config } = this.sequence;

                this.mailRecipient = config.recipient?.type;

                if (config.recipient?.type === 'custom') {
                    Object.entries(config.recipient.data)
                        .forEach(([key, value]) => {
                            const newId = Utils.createId();
                            this.recipients.push({
                                id: newId,
                                email: key,
                                name: value,
                                isNew: false,
                            });
                        });

                    this.addRecipient();
                    this.showRecipientEmails = true;
                }

                this.mailTemplateId = config.mailTemplateId;
                this.documentTypeIds = config.documentTypeIds;
            }
        },

        onClose() {
            this.$emit('modal-close');
        },

        getRecipientData() {
            const recipientData = {};
            if (this.mailRecipient !== 'custom') {
                return recipientData;
            }

            this.recipients.forEach(recipient => {
                if (!recipient.email && !recipient.name) {
                    return;
                }

                Object.assign(recipientData, {
                    [recipient.email]: recipient.name,
                });
            });
            return recipientData;
        },

        isRecipientGridError() {
            if (this.mailRecipient !== 'custom') {
                return false;
            }

            if (this.recipients.length === 1 &&
                !this.recipients[0].email &&
                !this.recipients[0].name) {
                this.validateRecipient(this.recipients[0], 0);
                return true;
            }

            const invalidItemIndex = this.recipients.filter(item => !item.isNew)
                .findIndex(recipient => (!recipient.name || !recipient.email || !emailValidation(recipient.email)));

            if (invalidItemIndex >= 0) {
                this.validateRecipient(this.recipients[invalidItemIndex], invalidItemIndex);
            }

            return invalidItemIndex >= 0;
        },

        onAddAction() {
            this.mailTemplateIdError = this.fieldError(this.mailTemplateId);
            this.recipientGridError = this.isRecipientGridError();

            if (this.mailTemplateIdError || this.recipientGridError) {
                return;
            }

            this.resetError();

            const sequence = {
                ...this.sequence,
                config: {
                    mailTemplateId: this.mailTemplateId,
                    documentTypeIds: this.documentTypeIds,
                    recipient: {
                        type: this.mailRecipient,
                        data: this.getRecipientData(),
                    },
                },
            };

            this.$nextTick(() => {
                this.$emit('process-finish', sequence);
            });
        },

        onCreateMailTemplate() {
            this.showCreateMailTemplateModal = true;
        },

        onCloseCreateMailTemplateModal() {
            this.showCreateMailTemplateModal = false;
        },

        onCreateMailTemplateSuccess(mailTemplate) {
            this.mailTemplateId = mailTemplate.id;
            this.onChangeMailTemplate(mailTemplate.id, mailTemplate);
        },

        onChangeMailTemplate(id, mailTemplate) {
            if (id) {
                this.mailTemplateIdError = null;
            }

            const currentMailTemplate = this.mailTemplates.find(item => item.id === id);
            if (!currentMailTemplate && mailTemplate) {
                Shopware.State.commit('swFlowState/setMailTemplates', [...this.mailTemplates, mailTemplate]);
            }
        },

        onChangeRecipient(recipient) {
            if (recipient === 'custom') {
                this.showRecipientEmails = true;
                this.addRecipient();
            } else {
                this.showRecipientEmails = false;
            }
        },

        addRecipient() {
            const newId = Utils.createId();

            this.recipients.push({
                id: newId,
                email: '',
                name: '',
                isNew: true,
            });

            this.$nextTick(() => {
                this.$refs.recipientsGrid.currentInlineEditId = newId;
                this.$refs.recipientsGrid.enableInlineEdit();
            });
        },

        saveRecipient(recipient) {
            const index = this.recipients.findIndex((item) => {
                return item.id === recipient.id;
            });

            if (this.validateRecipient(recipient, index)) {
                this.$nextTick(() => {
                    this.$refs.recipientsGrid.currentInlineEditId = recipient.id;
                    this.$refs.recipientsGrid.enableInlineEdit();
                });
                return;
            }

            if (recipient.isNew) {
                this.addRecipient();
                this.recipients[index].isNew = false;
            }

            this.resetError();
        },

        cancelSaveRecipient(recipient) {
            if (!recipient.isNew) {
                const index = this.recipients.findIndex((item) => {
                    return item.id === this.selectedRecipient.id;
                });

                // Reset data when saving is cancelled
                this.recipients[index] = this.selectedRecipient;
            } else {
                recipient.name = '';
                recipient.email = '';
            }

            this.resetError();
        },

        onEditRecipient(item) {
            const index = this.recipients.findIndex((recipient) => {
                return item.id === recipient.id;
            });

            // Recheck error in current item
            if (!item.name && !item.email) {
                this.$set(this.recipients, index, { ...item, errorName: null });
                this.$set(this.recipients, index, { ...item, errorMail: null });
            } else {
                this.validateRecipient(item, index);
            }

            this.$refs.recipientsGrid.currentInlineEditId = item.id;
            this.$refs.recipientsGrid.enableInlineEdit();
            this.selectedRecipient = { ...item };
        },

        onDeleteRecipient(itemIndex) {
            this.recipients.splice(itemIndex, 1);
        },

        fieldError(text) {
            if (!text) {
                return new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });
            }

            return null;
        },

        setNameError(name) {
            const error = !name
                ? new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                }) : null;

            return error;
        },

        setMailError(mail) {
            let error = null;

            if (!mail) {
                error = new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });
            }

            if (!emailValidation(mail)) {
                error = new ShopwareError({
                    code: 'INVALID_MAIL',
                });
            }

            return error;
        },

        validateRecipient(item, itemIndex) {
            const errorName = this.setNameError(item.name);
            const errorMail = this.setMailError(item.email);

            this.$set(this.recipients, itemIndex, {
                ...item,
                errorName,
                errorMail,
            });

            return errorName || errorMail;
        },

        resetError() {
            this.recipientGridError = null;
            this.recipients.forEach(item => {
                item.errorName = null;
                item.errorMail = null;
            });
        },

        allowDeleteRecipient(itemIndex) {
            return itemIndex !== this.recipients.length - 1;
        },
    },
});
