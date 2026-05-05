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

            await this.setEmailTemplateAccordingToDocumentType();

            this.loadTheLinksForA11y();
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

            if (localMailTemplate?.mailTemplateType?.templateData?.order && this?.order) {
                localMailTemplate.mailTemplateType.templateData.order = this.order;
            }

            this.subject = localMailTemplate.subject;

            if (!this.order.salesChannel || !this.order.salesChannel.mailHeaderFooterId) {
                this.content = await this.mailService.buildRenderPreview(
                    localMailTemplate.mailTemplateType,
                    localMailTemplate,
                );

                return;
            }

            const mailTemplateWithHeaderFooter = { ...localMailTemplate };

            const mailHeaderFooter = await this.mailHeaderFooterRepository.search(
                new Criteria(1, 1).addFilter(Criteria.equals('id', this.order.salesChannel.mailHeaderFooterId)),
            );

            if (mailHeaderFooter[0]?.headerHtml) {
                mailTemplateWithHeaderFooter.contentHtml =
                    mailHeaderFooter[0].headerHtml + mailTemplateWithHeaderFooter.contentHtml;
            }

            if (mailHeaderFooter[0]?.footerHtml) {
                mailTemplateWithHeaderFooter.contentHtml += mailHeaderFooter[0].footerHtml;
            }

            this.content = await this.mailService.buildRenderPreview(
                mailTemplateWithHeaderFooter.mailTemplateType,
                mailTemplateWithHeaderFooter,
            );
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
                await this.mailService.sendMailTemplate(
                    this.recipient,
                    `${this.order.orderCustomer.firstName} ${this.order.orderCustomer.lastName}`,
                    {
                        ...mailTemplate,
                        ...{
                            subject: this.subject,
                            recipient: this.recipient,
                        },
                    },
                    mediaCollection,
                    this.order.salesChannelId,
                    false,
                    [this.document.id],
                    {
                        order: this.order,
                        salesChannel: this.order.salesChannel,
                        document: this.document,
                        a11yDocuments: this.a11yDocuments,
                    },
                    null,
                    null,
                    apiContext,
                );
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-order.documentSendModal.errorMessage'),
                });

                this.$emit('modal-close');
            } finally {
                this.isLoading = false;
            }

            this.$emit('document-sent');
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
