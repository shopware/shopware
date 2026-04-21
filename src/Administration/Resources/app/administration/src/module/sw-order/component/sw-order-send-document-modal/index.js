import template from './sw-order-send-document-modal.html.twig';
import './sw-order-send-document-modal.scss';

/**
 * @sw-package checkout
 */

const { Filter } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;

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

        /** @deprecated tag:v6.8.0 - Method will be removed */
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

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.recipient = this.order.orderCustomer.email;

            this.setEmailTemplateAccordingToDocumentType();

            this.loadTheLinksForA11y();
        },

        setEmailTemplateAccordingToDocumentType() {
            const documentMailTemplateMapping = {
                invoice: 'invoice_mail',
                credit_note: 'credit_note_mail',
                delivery_note: 'delivery_mail',
                storno: 'cancellation_mail',
            };

            if (!documentMailTemplateMapping.hasOwnProperty(this.document.documentType.technicalName)) {
                return;
            }

            this.mailTemplateRepository
                .search(this.mailTemplateCriteria, { ...Shopware.Context.api, languageId: this.order.languageId })
                .then((result) => {
                    const mailTemplate = result
                        .filter(
                            (t) =>
                                t.mailTemplateType.technicalName ===
                                documentMailTemplateMapping[this.document.documentType.technicalName],
                        )
                        .first();

                    if (!mailTemplate) {
                        return;
                    }

                    this.mailTemplateId = mailTemplate.id;
                    this.onMailTemplateChange(mailTemplate.id, mailTemplate);
                });
        },

        onMailTemplateChange(mailTemplateId, mailTemplate) {
            if (mailTemplateId === null) {
                this.subject = '';
                this.content = '';

                return Promise.resolve();
            }

            const localMailTemplate = { ...mailTemplate };
            if (localMailTemplate?.mailTemplateType?.templateData?.order && this?.order) {
                localMailTemplate.mailTemplateType.templateData.order = this.order;
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
                    false,
                    apiContext,
                )
                .then((preview) => {
                    this.content = preview?.contentHtml?.content ?? '';
                });
        },

        onSendDocument() {
            this.isLoading = true;

            const apiContext = {
                ...Shopware.Context.api,
                languageId: this.order.languageId || Shopware.Context.api.languageId,
            };

            this.mailTemplateRepository
                .get(this.mailTemplateId, apiContext, this.mailTemplateSendCriteria)
                .then((mailTemplate) => {
                    const mediaCollection = new EntityCollection('/media', 'media', Shopware.Context.api);

                    mailTemplate.media.forEach((mediaAssoc) => {
                        if (mediaAssoc.languageId === Shopware.Context.api.languageId) {
                            mediaCollection.push(mediaAssoc.media);
                        }
                    });

                    this.mailService
                        .getDataAndSendMailTemplate(
                            {
                                recipients: {
                                    [this.recipient]:
                                        `${this.order.orderCustomer.firstName} ${this.order.orderCustomer.lastName}`,
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
                        )
                        .then(() => {
                            this.$emit('document-sent');
                        })
                        .catch(() => {
                            this.createNotificationError({
                                message: this.$t('sw-order.documentSendModal.errorMessage'),
                            });
                            this.$emit('modal-close');
                        })
                        .finally(() => {
                            this.isLoading = false;
                        });
                });
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
