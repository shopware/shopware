import template from './sw-flow-mail-send-modal.html.twig';
import './sw-flow-mail-send-modal.scss';

const {
    Component,
    Utils,
    Classes: { ShopwareError },
    Store,
} = Shopware;
const { Criteria } = Shopware.Data;
const { debounce } = Shopware.Utils;
const { mapState } = Component.getComponentHelper();

/**
 * @private
 * @sw-package after-sales
 */
export default {
    template,

    inject: [
        'repositoryFactory',
        'validationApiService',
    ],

    emits: [
        'modal-close',
        'process-finish',
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
            recipientMailIsValid: true,
            documentTypeIds: [],
            recipients: [],
            selectedRecipient: null,
            mailTemplateIdError: null,
            recipientGridError: null,
            replyTo: null,
            replyToError: null,
            isValidating: false,
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
                    label: this.$t('sw-flow.modals.mail.labelCustomer'),
                },
            ];
        },

        recipientAdmin() {
            return [
                {
                    value: 'admin',
                    label: this.$t('sw-flow.modals.mail.labelAdmin'),
                },
            ];
        },

        recipientCustom() {
            return [
                {
                    value: 'custom',
                    label: this.$t('sw-flow.modals.mail.labelCustom'),
                },
            ];
        },

        recipientDefault() {
            return [
                {
                    value: 'default',
                    label: this.$t('sw-flow.modals.mail.labelDefault'),
                },
            ];
        },

        recipientContactFormMail() {
            return [
                {
                    value: 'contactFormMail',
                    label: this.$t('sw-flow.modals.mail.labelContactFormMail'),
                },
            ];
        },

        recipientRevocationRequestFormMail() {
            return [
                {
                    value: 'revocationRequestCustomerFormMail',
                    label: this.$t('sw-flow.modals.mail.labelRevocationRequestFormMail'),
                },
            ];
        },

        entityAware() {
            return [
                'CustomerAware',
                'UserAware',
                'OrderAware',
                'CustomerGroupAware',
            ];
        },

        recipientOptions() {
            const allowedAwareOrigin = this.triggerEvent.aware ?? [];
            const allowAwareConverted = [];
            allowedAwareOrigin.forEach((aware) => {
                aware = aware.slice(aware.lastIndexOf('\\') + 1);
                const awareUpperCase = aware.charAt(0).toUpperCase() + aware.slice(1);
                if (!allowAwareConverted.includes(awareUpperCase)) {
                    allowAwareConverted.push(awareUpperCase);
                }
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

            if (this.triggerEvent.name === 'revocation_request.sent') {
                return [
                    ...this.recipientDefault,
                    ...this.recipientRevocationRequestFormMail,
                    ...this.recipientAdmin,
                    ...this.recipientCustom,
                ];
            }

            if (
                [
                    'newsletter.confirm',
                    'newsletter.register',
                    'newsletter.unsubscribe',
                ].includes(this.triggerEvent.name)
            ) {
                return [
                    ...this.recipientCustomer,
                    ...this.recipientAdmin,
                    ...this.recipientCustom,
                ];
            }

            const hasEntityAware = allowAwareConverted.some((allowedAware) => this.entityAware.includes(allowedAware));

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
            return [
                {
                    property: 'email',
                    label: 'sw-flow.modals.mail.columnRecipientMail',
                    inlineEdit: 'string',
                },
                {
                    property: 'name',
                    label: 'sw-flow.modals.mail.columnRecipientName',
                    inlineEdit: 'string',
                },
            ];
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
            return this.replyToSelection === 'custom';
        },

        ...mapState(
            () => Store.get('swFlow'),
            [
                'mailTemplates',
                'triggerEvent',
                'triggerActions',
            ],
        ),
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
                    Object.entries(config.recipient.data).forEach(
                        ([
                            key,
                            value,
                        ]) => {
                            const newId = Utils.createId();
                            this.recipients.push({
                                id: newId,
                                email: key,
                                name: value,
                                isNew: false,
                                isMailValid: true,
                            });
                        },
                    );

                    this.showRecipientEmails = true;
                }

                if (config.replyTo) {
                    this.replyTo = config.replyTo;
                }

                this.mailTemplateId = config.mailTemplateId;
                this.documentTypeIds = config.documentTypeIds;
            }
        },

        onRecipientsGridMounted() {
            this.addEmptyRecipient();
        },

        onClose() {
            this.$emit('modal-close');
        },

        getRecipientData() {
            const recipientData = {};
            if (this.mailRecipient !== 'custom') {
                return recipientData;
            }

            this.recipients.forEach((recipient) => {
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

            if (this.recipients.length === 1 && !this.recipients[0].email && !this.recipients[0].name) {
                this.applyValidationResult(this.recipients[0], 0);
                return true;
            }

            const invalidItemIndex = this.recipients
                .filter((item) => !item.isNew)
                .findIndex((recipient) => !recipient.name || !recipient.email || !recipient.isMailValid);

            if (invalidItemIndex >= 0) {
                this.applyValidationResult(this.recipients[invalidItemIndex], invalidItemIndex);
            }

            return invalidItemIndex >= 0;
        },

        onAddAction() {
            this.mailTemplateIdError = this.mailTemplateError(this.mailTemplateId);
            this.recipientGridError = this.isRecipientGridError();
            if (this.mailTemplateIdError || this.replyToError || this.recipientGridError || this.isValidating) {
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

            const currentMailTemplate = this.mailTemplates.find((item) => item.id === id);
            if (!currentMailTemplate && mailTemplate) {
                Shopware.Store.get('swFlow').mailTemplates = [
                    ...this.mailTemplates,
                    mailTemplate,
                ];
            }
        },

        onChangeRecipient(recipient) {
            if (recipient === 'custom') {
                this.showRecipientEmails = true;
            } else {
                this.showRecipientEmails = false;
            }
        },

        debouncedIsEmailValid: debounce(function emailIsValid(recipient, originKey) {
            const email = typeof recipient === 'string' ? recipient : recipient?.email || '';

            this.isValidating = true;

            this.validationApiService.validateEmailAddress(email).then((isValid) => {
                this.handleDebouncedResponse(recipient, isValid, originKey);
            });
        }, 500),

        handleDebouncedResponse(recipient, isValid, originKey) {
            switch (originKey) {
                case 'grid':
                    this.handleGridResponse(recipient, isValid);
                    break;
                case 'replyTo':
                    this.handleReplyToResponse(isValid);
                    break;
                default:
            }

            this.isValidating = false;
        },

        handleGridResponse(recipient, isValid) {
            const index = this.getRecipientIndex(recipient);
            this.recipients[index].isMailValid = isValid;
        },

        handleReplyToResponse(isValid) {
            if (isValid) {
                this.replyToError = null;
                return;
            }

            this.replyToError = new ShopwareError({
                code: 'INVALID_MAIL',
            });
        },

        addEmptyRecipient() {
            const emptyRecipientIndex = this.getEmptyRecipientIndex();
            if (emptyRecipientIndex >= 0) {
                const recipient = this.recipients[emptyRecipientIndex];
                this.enableInlineEdit(recipient);

                return;
            }

            const recipient = this.createEmptyRecipient();
            this.recipients.push(recipient);
            this.enableInlineEdit(recipient);
        },

        createEmptyRecipient() {
            return {
                id: Utils.createId(),
                email: '',
                name: '',
                isNew: true,
            };
        },

        getEmptyRecipientIndex() {
            return this.recipients.findIndex((item) => {
                return item.email === '' && item.name === '' && item.isNew === true;
            });
        },

        saveRecipient(recipient) {
            if (this.isValidating) {
                this.enableInlineEdit(recipient);

                return;
            }

            const index = this.getRecipientIndex(recipient);
            if (this.applyValidationResult(recipient, index)) {
                this.enableInlineEdit(recipient);

                return;
            }

            if (recipient.isNew) {
                this.addEmptyRecipient();
                this.recipients[index].isNew = false;
            }

            this.resetError();
        },

        enableInlineEdit(recipient) {
            this.$nextTick().then(() => {
                this.$refs.recipientsGrid.currentInlineEditId = recipient.id;
                this.$refs.recipientsGrid.enableInlineEdit();
            });
        },

        getRecipientIndex(recipient) {
            return this.recipients.findIndex((item) => {
                return item.id === recipient.id;
            });
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
                this.recipients[index] = { ...item, errorName: null };
                this.recipients[index] = { ...item, errorMail: null };
            } else {
                this.isValidating = true;
                this.validationApiService
                    .validateEmailAddress(item.email)
                    .then((isValid) => {
                        item.isMailValid = isValid;
                        this.applyValidationResult(item, index);
                    })
                    .finally(() => {
                        this.isValidating = false;
                    });
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

        handleInvalidName(recipient) {
            if (!recipient.name) {
                recipient.errorName = new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });
            } else {
                recipient.errorName = null;
            }
        },

        handleInvalidMail(recipient) {
            let isValid = true;
            if (!recipient.email) {
                isValid = false;
                recipient.errorMail = new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });
            }

            if (!recipient.isMailValid) {
                isValid = false;
                recipient.errorMail = new ShopwareError({
                    code: 'INVALID_MAIL',
                });
            }

            if (isValid) {
                recipient.errorMail = null;
            }
        },

        applyValidationResult(item, itemIndex) {
            this.handleInvalidName(item);
            this.handleInvalidMail(item);

            this.recipients[itemIndex].errorName = item.errorName;
            this.recipients[itemIndex].errorMail = item.errorMail;
            this.recipients[itemIndex].isMailValid = item.isMailValid;

            return this.recipients[itemIndex].errorName || this.recipients[itemIndex].errorMail;
        },

        resetError() {
            this.recipientGridError = null;
            this.recipients.forEach((item) => {
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

            return this.$t(snippet, 0, data);
        },
    },
};
