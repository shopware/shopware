import { email as emailValidation } from 'src/core/service/validation.service';
import template from './sw-flow-mail-send-modal.html.twig';
import './sw-flow-mail-send-modal.scss';

const { Component, Utils, Classes: { ShopwareError } } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState } = Component.getComponentHelper();

/**
 * @private
 * @package business-ops
 */
export default {
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
            replyTo: null,
            replyToError: null,
        };
    },

    computed: {
        mailTemplateCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('mailTemplateType');

            return criteria;
        },

        documentTypeRepository() {
            return this.repositoryFactory.create('document_type');
        },

        isNewMail() {
            return !this.sequence?.id;
        },

        recipientCustomer() {
            return [
                {
                    value: 'default',
                    label: this.$tc('sw-flow.modals.mail.labelCustomer'),
                },
            ];
        },

        recipientAdmin() {
            return [
                {
                    value: 'admin',
                    label: this.$tc('sw-flow.modals.mail.labelAdmin'),
                },
            ];
        },

        recipientCustom() {
            return [
                {
                    value: 'custom',
                    label: this.$tc('sw-flow.modals.mail.labelCustom'),
                },
            ];
        },

        recipientDefault() {
            return [
                {
                    value: 'default',
                    label: this.$tc('sw-flow.modals.mail.labelDefault'),
                },
            ];
        },

        recipientContactFormMail() {
            return [
                {
                    value: 'contactFormMail',
                    label: this.$tc('sw-flow.modals.mail.labelContactFormMail'),
                },
            ];
        },

        entityAware() {
            return ['CustomerAware', 'UserAware', 'OrderAware', 'CustomerGroupAware'];
        },

        recipientOptions() {
            const allowedAwareOrigin = this.triggerEvent.aware ?? [];
            const allowAwareConverted = [];
            allowedAwareOrigin.forEach(aware => {
                allowAwareConverted.push(aware.slice(aware.lastIndexOf('\\') + 1));
            });

            if (allowAwareConverted.length === 0) {
                return this.recipientCustom;
            }

            if (this.triggerEvent.name === 'contact_form.send') {
                return [
                    ...this.recipientDefault,
                    ...this.recipientContactFormMail,
                    ...this.recipientAdmin,
                    ...this.recipientCustom,
                ];
            }
            if (['newsletter.confirm', 'newsletter.register', 'newsletter.unsubscribe']
                .includes(this.triggerEvent.name)) {
                return [
                    ...this.recipientCustomer,
                    ...this.recipientAdmin,
                    ...this.recipientCustom,
                ];
            }

            const hasEntityAware = allowAwareConverted.some(allowedAware => this.entityAware.includes(allowedAware));

            if (hasEntityAware) {
                return [
                    ...this.recipientCustomer,
                    ...this.recipientAdmin,
                    ...this.recipientCustom,
                ];
            }

            return [
                ...this.recipientAdmin,
                ...this.recipientCustom,
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

        replyToOptions() {
            if (this.triggerEvent.name === 'contact_form.send') {
                return [
                    ...this.recipientDefault,
                    ...this.recipientContactFormMail,
                    ...this.recipientCustom,
                ];
            }

            return [
                ...this.recipientDefault,
                ...this.recipientCustom,
            ];
        },

        replyToSelection() {
            switch (this.replyTo) {
                case null:
                    return 'default';
                case 'contactFormMail':
                    return 'contactFormMail';
                default:
                    return 'custom';
            }
        },

        showReplyToField() {
            return !(this.replyTo === null || this.replyTo === 'contactFormMail');
        },

        ...mapState('swFlowState', ['mailTemplates', 'triggerEvent', 'triggerActions']),
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.mailRecipient = this.recipientOptions[0].value;

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

                if (config.replyTo) {
                    this.replyTo = config.replyTo;
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
            this.mailTemplateIdError = this.mailTemplateError(this.mailTemplateId);
            if (this.showReplyToField) {
                this.replyToError = this.setMailError(this.replyTo);
            }
            this.recipientGridError = this.isRecipientGridError();

            if (this.mailTemplateIdError || this.replyToError || this.recipientGridError) {
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
                    replyTo: this.replyTo,
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

        mailTemplateError(mailTemplate) {
            if (!mailTemplate) {
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

        changeShowReplyToField(value) {
            switch (value) {
                case 'default':
                    this.replyToError = null;
                    this.replyTo = null;

                    return;
                case 'contactFormMail':
                    this.replyToError = null;
                    this.replyTo = 'contactFormMail';

                    return;
                default:
                    this.replyTo = '';
            }
        },

        buildReplyToTooltip(snippet) {
            const route = { name: 'sw.settings.basic.information.index' };
            const routeData = this.$router.resolve(route);

            const data = {
                settingsLink: routeData.href,
            };

            return this.$tc(snippet, 0, data);
        },
    },
};
