import template from './sw-order-send-document-modal.html.twig';
import './sw-order-send-document-modal.scss';

/**
 * @package checkout
 */

const { Filter } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'mailService',
        'repositoryFactory',
    ],

    emits: ['modal-close', 'document-sent'],

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
                Criteria.equalsAny(
                    'mailTemplateType.technicalName',
                    [
                        'delivery_mail',
                        'invoice_mail',
                        'credit_note_mail',
                        'cancellation_mail',
                    ],
                ),
            );

            return criteria;
        },

        mailTemplateSendCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('mailTemplateType');

            return criteria;
        },

        primaryActionDisabled() {
            return this.mailTemplateId === null || this.subject.length <= 0 || this.recipient.length <= 0;
        },

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

            this.mailTemplateRepository.search(this.mailTemplateCriteria, Shopware.Context.api).then((result) => {
                const mailTemplate = result.filter(
                    t => t.mailTemplateType.technicalName === documentMailTemplateMapping[
                        this.document.documentType.technicalName
                    ],
                ).first();

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

            this.subject = mailTemplate.subject;

            if (!this.order.salesChannel || !this.order.salesChannel.mailHeaderFooterId) {
                return this.mailService.buildRenderPreview(mailTemplate.mailTemplateType, mailTemplate).then((result) => {
                    this.content = result;
                });
            }

            const mailTemplateWithHeaderFooter = { ...mailTemplate };
            return this.mailHeaderFooterRepository.search(
                new Criteria(1, 1)
                    .addFilter(Criteria.equals('id', this.order.salesChannel.mailHeaderFooterId)),
            ).then((mailHeaderFooter) => {
                if (mailHeaderFooter[0].headerHtml) {
                    mailTemplateWithHeaderFooter.contentHtml =
                        mailHeaderFooter[0].headerHtml + mailTemplateWithHeaderFooter.contentHtml;
                }

                if (mailHeaderFooter[0].footerHtml) {
                    mailTemplateWithHeaderFooter.contentHtml += mailHeaderFooter[0].footerHtml;
                }

                return this.mailService.buildRenderPreview(
                    mailTemplateWithHeaderFooter.mailTemplateType,
                    mailTemplateWithHeaderFooter,
                );
            }).then((result) => {
                this.content = result;
            });
        },

        onSendDocument() {
            this.isLoading = true;

            this.mailTemplateRepository.get(this.mailTemplateId, Shopware.Context.api, this.mailTemplateSendCriteria)
                .then((mailTemplate) => {
                    this.mailService.sendMailTemplate(
                        this.recipient,
                        `${this.order.orderCustomer.firstName} ${this.order.orderCustomer.lastName}`,
                        {
                            ...mailTemplate,
                            ...{
                                subject: this.subject,
                                recipient: this.recipient,
                            },
                        },
                        {
                            getIds: () => {},
                        },
                        this.order.salesChannelId,
                        false,
                        [this.document.id],
                        {
                            order: this.order,
                            salesChannel: this.order.salesChannel,
                        },
                    ).catch(() => {
                        this.createNotificationError({
                            message: this.$tc('sw-order.documentSendModal.errorMessage'),
                        });
                        this.$emit('modal-close');
                    }).then(() => {
                        this.$emit('document-sent');
                    }).finally(() => {
                        this.isLoading = false;
                    });
                });
        },
    },
};
