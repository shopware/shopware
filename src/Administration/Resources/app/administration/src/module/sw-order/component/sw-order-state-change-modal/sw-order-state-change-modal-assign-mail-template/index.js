import template from './sw-order-state-change-modal-assign-mail-template.html.twig';
import './sw-order-state-change-modal-assign-mail-template.scss';

const { Criteria } = Shopware.Data;
const { Component } = Shopware;

Component.register('sw-order-state-change-modal-assign-mail-template', {
    template,

    inject: ['repositoryFactory'],

    props: {
        order: {
            type: Object,
            required: true
        },

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
            userHasSetMailTemplate: false,
            selectedMailTemplateId: null,
            mailTemplates: null,
            allTechnicalNames: null,
            searchTerm: null
        };
    },

    computed: {
        mailTemplateRepository() {
            return this.repositoryFactory.create('mail_template');
        },

        mailTemplateSalesChannelAssociationRepository() {
            return this.repositoryFactory.create('mail_template_sales_channel');
        },

        getSelectedMailTemplateCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('mailTemplateType');

            return criteria;
        },

        mailTemplateGridColumns() {
            return [
                {
                    property: 'radioButtons',
                    label: null,
                    rawData: true,
                    sortable: false
                },
                {
                    property: 'mailTemplateType.name',
                    label: 'sw-order.assignMailTemplateCard.gridColumnType',
                    rawData: true,
                    sortable: false
                },
                {
                    property: 'description',
                    label: 'sw-order.assignMailTemplateCard.gridColumnDescription',
                    rawData: true,
                    sortable: true
                }
            ];
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        mailTemplateSelectionCriteria() {
            const criteria = new Criteria();

            criteria.addFilter(
                Criteria.equals('mailTemplateType.technicalName', this.technicalName)
            );

            criteria.addAssociation('mailTemplateType');

            return criteria;
        },

        getSelectedMailTemplate(mailTemplateId) {
            return this.mailTemplateRepository
                .get(
                    mailTemplateId,
                    Shopware.Context.api,
                    this.getSelectedMailTemplateCriteria
                );
        },

        onChangeMailTemplate(mailTemplateId) {
            if (!mailTemplateId) {
                return;
            }
            this.selectedMailTemplateId = mailTemplateId;
            this.userHasSetMailTemplate = true;
        },

        onConfirm() {
            this.getSelectedMailTemplate(this.selectedMailTemplateId).then((mailTemplate) => {
                const mailTemplateSalesChannel = this.mailTemplateSalesChannelAssociationRepository.create();

                mailTemplateSalesChannel.salesChannelId = this.order.salesChannelId;
                mailTemplateSalesChannel.mailTemplateId = this.selectedMailTemplateId;
                mailTemplateSalesChannel.mailTemplateTypeId = mailTemplate.mailTemplateTypeId;
                this.mailTemplateSalesChannelAssociationRepository.save(mailTemplateSalesChannel, Shopware.Context.api);
            });

            this.$emit('on-assigned-mail-template');
        },

        onCreateMailTemplate() {
            const closeModal = new Promise((resolve) => {
                resolve(this.$emit('on-create-mail-template'));
            });

            closeModal.then(() => {
                this.$router.push({
                    name: 'sw.mail.template.create'
                });
            });
        },

        fillValues() {
            const searchTerm = this.searchTerm;
            const criteria = this.mailTemplateSelectionCriteria().setLimit(10);

            if (searchTerm) {
                criteria.addFilter(
                    Criteria.contains('description', searchTerm)
                );
            }

            const allTechnicalNamesCriteria = new Criteria();

            this.mailTemplateSalesChannelAssociationRepository
                .search(allTechnicalNamesCriteria, Shopware.Context.api)
                .then((items) => {
                    this.allTechnicalNames = items;
                });

            this.mailTemplateRepository.search(criteria, Shopware.Context.api).then((items) => {
                this.total = items.total;
                this.mailTemplates = items;
                this.isLoading = false;
            });
        },

        onColumnSort() {
            this.userHasSetMailTemplate = false;
        },

        onSearchTermChange(term) {
            this.searchTerm = term;

            this.fillValues();
        },

        createdComponent() {
            this.fillValues();
        }
    }
});
