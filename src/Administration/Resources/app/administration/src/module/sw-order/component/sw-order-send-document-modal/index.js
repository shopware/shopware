import template from './sw-order-send-document-modal.html.twig';
import './sw-order-send-document-modal.scss';
import { DOCUMENT_TYPES } from '../../order.types';

const { Filter } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;

const DOCUMENT_MAIL_TEMPLATES = {
    INVOICE: 'invoice_mail',
    DELIVERY_NOTE: 'delivery_mail',
    CREDIT_NOTE: 'credit_note_mail',
    CANCELLATION_INVOICE: 'cancellation_mail',
};

/**
 * @private
 */
export const DOCUMENT_MAIL_TEMPLATE_MAPPING = {
    [DOCUMENT_TYPES.INVOICE]: DOCUMENT_MAIL_TEMPLATES.INVOICE,
    [DOCUMENT_TYPES.ZUGFERD_INVOICE]: DOCUMENT_MAIL_TEMPLATES.INVOICE,
    [DOCUMENT_TYPES.ZUGFERD_EMBEDDED_INVOICE]: DOCUMENT_MAIL_TEMPLATES.INVOICE,
    [DOCUMENT_TYPES.DELIVERY_NOTE]: DOCUMENT_MAIL_TEMPLATES.DELIVERY_NOTE,
    [DOCUMENT_TYPES.CREDIT_NOTE]: DOCUMENT_MAIL_TEMPLATES.CREDIT_NOTE,
    [DOCUMENT_TYPES.ZUGFERD_CREDIT_NOTE]: DOCUMENT_MAIL_TEMPLATES.CREDIT_NOTE,
    [DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CREDIT_NOTE]: DOCUMENT_MAIL_TEMPLATES.CREDIT_NOTE,
    [DOCUMENT_TYPES.CANCELLATION_INVOICE]: DOCUMENT_MAIL_TEMPLATES.CANCELLATION_INVOICE,
    [DOCUMENT_TYPES.ZUGFERD_CANCELLATION_INVOICE]: DOCUMENT_MAIL_TEMPLATES.CANCELLATION_INVOICE,
    [DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CANCELLATION_INVOICE]: DOCUMENT_MAIL_TEMPLATES.CANCELLATION_INVOICE,
};

/**
 * @sw-package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'mailService',
        'repositoryFactory',
    ],

    emits: [
        'modal-close',
        'document-sent',
    ],

    mixins: [
        'notification',
    ],

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
            mailTemplateId: null,
            subject: '',
            recipient: '',
            content: '',
            a11yDocuments: [],
        };
    },

    computed: {
        truncateFilter() {
            return Filter.getByName('truncate');
        },

        mailTemplateRepository() {
            return this.repositoryFactory.create('mail_template');
        },

        mailHeaderFooterRepository() {
            return this.repositoryFactory.create('mail_header_footer');
        },

        mailTemplateCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('mailTemplateType');
            criteria.addFilter(
                Criteria.equalsAny('mailTemplateType.technicalName', [
                    'delivery_mail',
                    'invoice_mail',
                    'credit_note_mail',
                    'cancellation_mail',
                ]),
            );

            return criteria;
        },

        mailTemplateSendCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('mailTemplateType');
            criteria.addAssociation('media.media');

            return criteria;
        },

        primaryActionDisabled() {
            return this.mailTemplateId === null || this.subject.length <= 0 || this.recipient.length <= 0;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, because the filter is unused
         */
        dateFilter() {
            return Shopware.Filter.getByName('date');
        },
    },

    async created() {
        await this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.recipient = this.order.orderCustomer.email;

            this.loadTheLinksForA11y();

            await this.setEmailTemplateAccordingToDocumentType();
        },

        async setEmailTemplateAccordingToDocumentType() {
            const type = this.document.documentType.technicalName;

            if (!(type in DOCUMENT_MAIL_TEMPLATE_MAPPING)) {
                return;
            }

            const template = DOCUMENT_MAIL_TEMPLATE_MAPPING[type];

            const criteria = new Criteria(1, 1)
                .addAssociation('mailTemplateType')
                .addFilter(Criteria.equals('mailTemplateType.technicalName', template));

            const context = {
                ...Shopware.Context.api,
                languageId: this.order.languageId,
            };

            const result = await this.mailTemplateRepository.search(criteria, context);

            if (result?.length !== 1) {
                return;
            }

            const mailTemplate = result.first();
            this.mailTemplateId = mailTemplate.id;

            await this.onMailTemplateChange(mailTemplate.id, mailTemplate);
        },

        async onMailTemplateChange(mailTemplateId, mailTemplate) {
            if (mailTemplateId === null) {
                this.subject = '';
                this.content = '';

                return;
            }

            const localMailTemplate = { ...mailTemplate };

            if (!Shopware.Feature.isActive('v6.8.0.0')) {
                if (localMailTemplate?.mailTemplateType?.templateData?.order && this?.order) {
                    localMailTemplate.mailTemplateType.templateData.order = this.order;
                }
            }

            const apiContext = {
                ...Shopware.Context.api,
                languageId: this.order.languageId || Shopware.Context.api.languageId,
            };

            this.subject = localMailTemplate.subject;

            return this.mailService
                .previewMailTemplate(
                    localMailTemplate.id,
                    {
                        order: this.order.id,
                        salesChannel: this.order.salesChannelId,
                    },
                    {
                        a11yDocuments: this.a11yDocuments,
                    },
                    this.order.salesChannelId,
                    true,
                    true,
                    apiContext,
                )
                .then((preview) => {
                    this.content = preview?.contentHtml?.content ?? '';
                });
        },

        async onSendDocument() {
            this.isLoading = true;

            const apiContext = {
                ...Shopware.Context.api,
                languageId: this.order.languageId || Shopware.Context.api.languageId,
            };

            const mailTemplate = await this.mailTemplateRepository.get(
                this.mailTemplateId,
                apiContext,
                this.mailTemplateSendCriteria,
            );

            const mediaCollection = new EntityCollection('/media', 'media', Shopware.Context.api);

            mailTemplate.media.forEach((mediaAssoc) => {
                if (mediaAssoc.languageId === Shopware.Context.api.languageId) {
                    mediaCollection.push(mediaAssoc.media);
                }
            });

            try {
                await this.mailService.getDataAndSendMailTemplate(
                    {
                        recipients: {
                            [this.recipient]: `${this.order.orderCustomer.firstName} ${this.order.orderCustomer.lastName}`,
                        },
                        salesChannelId: this.order.salesChannelId,
                        mediaIds: Array.from(mediaCollection.getIds()),
                        subject: this.subject,
                        senderMail: mailTemplate.senderMail,
                        senderName: mailTemplate.senderName ?? mailTemplate.translated?.senderName,
                        documentIds: [this.document.id],
                        testMode: false,
                        mailTemplateId: mailTemplate.id,
                        entities: {
                            order: this.order.id,
                            salesChannel: this.order.salesChannelId,
                        },
                        templateData: {
                            a11yDocuments: this.a11yDocuments,
                        },
                    },
                    apiContext,
                );

                this.$emit('document-sent');
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-order.documentSendModal.errorMessage'),
                });

                this.$emit('modal-close');
            } finally {
                this.isLoading = false;
            }
        },

        loadTheLinksForA11y() {
            if (!this.document?.documentA11yMediaFile) {
                return;
            }

            this.a11yDocuments.push({
                documentId: this.document.id,
                deepLinkCode: this.document.deepLinkCode,
                fileExtension: this.document.documentA11yMediaFile.fileExtension,
            });
        },
    },
};
