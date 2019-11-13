import template from './sw-mail-template-detail.html.twig';
import './sw-mail-template-detail.scss';

const { Component, Mixin } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;
const { warn } = Shopware.Utils.debug;

Component.register('sw-mail-template-detail', {
    template,

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification')
    ],

    inject: ['mailService', 'entityMappingService', 'repositoryFactory'],

    data() {
        return {
            mailTemplate: false,
            testerMail: '',
            mailTemplateId: null,
            isLoading: false,
            isSaveSuccessful: false,
            mailTemplateType: {},
            mailTemplateSalesChannels: null,
            mailTemplateSalesChannelsAssoc: {},
            salesChannelTypeCriteria: null,
            selectedType: {},
            editorConfig: {
                enableBasicAutocompletion: true
            }
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.mailTemplateType, 'name');
        },

        mailTemplateRepository() {
            return this.repositoryFactory.create('mail_template');
        },

        mailTemplateSalesChannelAssociationRepository() {
            return this.repositoryFactory.create('mail_template_sales_channel');
        },

        mailTemplateSalesChannelAssociationStore() {
            return this.mailTemplate.getAssociation('salesChannels');
        },

        outerCompleterFunction() {
            return (function completerWrapper(entityMappingService, innerMailTemplateType) {
                function completerFunction(prefix) {
                    const properties = [];
                    Object.keys(
                        entityMappingService.getEntityMapping(
                            prefix, innerMailTemplateType.availableEntities
                        )
                    ).forEach((val) => {
                        properties.push({
                            value: val
                        });
                    });
                    return properties;
                }
                return completerFunction;
            }(this.entityMappingService, this.mailTemplateType));
        },

        mailTemplateTypeRepository() {
            return this.repositoryFactory.create('mail_template_type');
        },

        testMailRequirementsMet() {
            return this.testerMail &&
                this.mailTemplate.subject &&
                this.mailTemplate.contentPlain &&
                this.mailTemplate.contentHtml &&
                this.mailTemplate.senderName;
        }
    },

    created() {
        this.createdComponent();
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.mailTemplateId = this.$route.params.id;
                this.loadEntityData();
            }
        },

        loadEntityData() {
            const criteria = new Criteria();
            criteria.addAssociation('salesChannels.salesChannel');
            criteria.addAssociation('mailTemplateType');
            this.isLoading = true;
            this.mailTemplateRepository.get(this.mailTemplateId, Shopware.Context.api, criteria).then((item) => {
                this.mailTemplate = item;
                this.mailTemplateSalesChannels = this.createSalesChannelCollection();
                this.mailTemplate.salesChannels.forEach((salesChannelAssoc) => {
                    this.mailTemplateSalesChannels.push(salesChannelAssoc.salesChannel);
                });
                this.onChangeType(this.mailTemplate.mailTemplateType.id);
            });
        },

        createSalesChannelCollection() {
            return new EntityCollection('/sales-channel', 'sales_channel', Shopware.Context.api);
        },

        getMailTemplateType() {
            if (this.mailTemplate.mailTemplateTypeId) {
                this.mailTemplateTypeRepository.get(
                    this.mailTemplate.mailTemplateTypeId,
                    Shopware.Context.api
                ).then((item) => {
                    this.mailTemplateType = item;
                    this.$refs.htmlEditor.defineAutocompletion(this.outerCompleterFunction);
                    this.$refs.plainEditor.defineAutocompletion(this.outerCompleterFunction);
                });
            }
        },

        abortOnLanguageChange() {
            return this.mailTemplateRepository.hasChanges(this.mailTemplate);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            const updatePromises = [];
            const mailTemplateSubject = this.mailTemplate.subject || this.placeholder(this.mailTemplate, 'subject');

            this.isSaveSuccessful = false;
            this.isLoading = true;
            this.handleSalesChannel();
            this.mailTemplateSalesChannelsAssoc.forEach((salesChannelAssoc) => {
                updatePromises.push(
                    this.mailTemplateSalesChannelAssociationRepository.save(salesChannelAssoc, Shopware.Context.api)
                );
            });
            updatePromises.push(this.mailTemplateRepository.save(this.mailTemplate, Shopware.Context.api).then(() => {
                this.mailTemplate.salesChannels.forEach((salesChannelAssoc) => {
                    if (
                        typeof salesChannelAssoc.salesChannelId !== 'undefined' &&
                        !this.salesChannelIsSelected(salesChannelAssoc.salesChannelId)
                    ) {
                        updatePromises.push(
                            this.mailTemplateSalesChannelAssociationRepository.delete(
                                salesChannelAssoc.id, Shopware.Context.api
                            )
                        );
                    }
                });
            }).catch((error) => {
                let errormsg = '';
                this.isLoading = false;
                if (error.response.data.errors.length > 0) {
                    errormsg = '<br/>Error Message: "'.concat(error.response.data.errors[0].detail).concat('"');
                }
                this.createNotificationError({
                    title: this.$tc('sw-mail-template.detail.titleSaveError'),
                    message: this.$tc(
                        'sw-mail-template.detail.messageSaveError',
                        0,
                        { subject: mailTemplateSubject }
                    ) + errormsg
                });
            }));
            Promise.all(updatePromises).then(() => {
                this.loadEntityData();
            });
        },

        onClickTestMailTemplate() {
            const notificationTestMailSuccess = {
                title: this.$tc('sw-mail-template.general.notificationTestMailSuccessTitle'),
                message: this.$tc('sw-mail-template.general.notificationTestMailSuccessMessage')
            };

            const notificationTestMailError = {
                title: this.$tc('sw-mail-template.general.notificationTestMailErrorTitle'),
                message: this.$tc('sw-mail-template.general.notificationTestMailErrorMessage')
            };

            const notificationTestMailErrorSalesChannel = {
                title: this.$tc('sw-mail-template.general.notificationTestMailErrorTitle'),
                message: this.$tc('sw-mail-template.general.notificationTestMailSalesChannelErrorMessage')
            };

            if (this.mailTemplate.salesChannels.length) {
                this.mailTemplate.salesChannels.forEach((salesChannelAssoc) => {
                    let salesChannelId = '';
                    if (typeof salesChannelAssoc === 'object') {
                        salesChannelId = salesChannelAssoc.salesChannel.id;
                    } else {
                        salesChannelId = salesChannelAssoc;
                    }
                    this.mailService.testMailTemplateById(
                        this.testerMail,
                        this.mailTemplate,
                        salesChannelId
                    ).then(() => {
                        this.createNotificationSuccess(notificationTestMailSuccess);
                    }).catch((exception) => {
                        this.createNotificationError(notificationTestMailError);
                        warn(this._name, exception.message, exception.response);
                    });
                });
            } else {
                this.createNotificationError(notificationTestMailErrorSalesChannel);
            }
        },

        onChangeType(id) {
            if (!id) {
                this.selectedType = {};
                return;
            }
            this.isLoading = true;
            this.getMailTemplateType();
            this.mailTemplateTypeRepository.get(id, Shopware.Context.api).then((item) => {
                this.selectedType = item;
            });

            // Reset the selected salesChannel
            this.mailTemplateSalesChannels = this.createSalesChannelCollection();
            const mailTemplateSalesChannelsEntry = this.repositoryFactory.create('mail_template_sales_channel');
            const mailTemplateSalesChannelCriteria = new Criteria();
            mailTemplateSalesChannelCriteria.addFilter(
                Criteria.equals('mailTemplateTypeId', id)
            );
            mailTemplateSalesChannelsEntry.search(mailTemplateSalesChannelCriteria, Shopware.Context.api).then(
                (responseSalesChannels) => {
                    const assignedSalesChannelIds = [];
                    responseSalesChannels.forEach((salesChannel) => {
                        if (salesChannel.salesChannelId !== null) {
                            assignedSalesChannelIds.push(salesChannel.salesChannelId);
                        }
                    });
                    this.getPossibleSalesChannels(assignedSalesChannelIds);
                }
            );
            this.outerCompleterFunction();
        },
        getPossibleSalesChannels(assignedSalesChannelIds) {
            this.setSalesChannelCriteria(assignedSalesChannelIds);
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('mailTemplateId', this.mailTemplate.id));
            criteria.addAssociation('salesChannel');
            this.mailTemplateSalesChannelAssociationRepository.search(
                criteria,
                Shopware.Context.api
            ).then((responseAssoc) => {
                this.enrichAssocStores(responseAssoc);
            });
        },
        setSalesChannelCriteria(assignedSalesChannelIds) {
            this.salesChannelTypeCriteria = new Criteria();
            if (assignedSalesChannelIds.length > 0) {
                // get all salesChannels which are not assigned to this mailTemplateType
                // and all SalesChannels already assigned to the current mailTemplate if type not changed
                if (this.mailTemplate.mailTemplateTypeId === this.selectedType.id) {
                    this.salesChannelTypeCriteria.addFilter(Criteria.multi('OR',
                        [
                            Criteria.equals('mailTemplates.id', null),
                            Criteria.not(
                                'AND',
                                [Criteria.equalsAny('id', assignedSalesChannelIds)]
                            ),
                            Criteria.equals(
                                'mailTemplates.mailTemplate.id', this.mailTemplate.id
                            )
                        ]));
                } else { // type changed so only get free saleschannels
                    this.salesChannelTypeCriteria.addFilter(Criteria.multi('OR',
                        [
                            Criteria.equals('mailTemplates.id', null),
                            Criteria.not(
                                'AND',
                                [Criteria.equalsAny('id', assignedSalesChannelIds)]
                            )
                        ]));
                }
            }
            // Reset the results of the select field. So it fetches new results with the new criteria
            this.$refs.mailTemplateSalesChannelSelect.resetResultCollection();
        },
        enrichAssocStores(responseAssoc) {
            this.mailTemplateSalesChannelsAssoc = responseAssoc;
            this.mailTemplateSalesChannelsAssoc.forEach((salesChannelAssoc) => {
                // Check if sales channel id already exists in mailTemplateSalesChannel
                const found = this.mailTemplateSalesChannels.some((item) => {
                    return item.id === salesChannelAssoc.salesChannelId;
                });
                if (salesChannelAssoc.salesChannelId !== null && !found) {
                    this.mailTemplateSalesChannels.push(salesChannelAssoc.salesChannel);
                }
            });
            this.isLoading = false;
        },
        handleSalesChannel() {
            // check selected saleschannels and associate to config
            const selectedIds = this.mailTemplateSalesChannels.getIds();
            if (selectedIds && selectedIds.length > 0) {
                selectedIds.forEach((salesChannelId) => {
                    if (!this.mailTemplateHasSaleschannel(salesChannelId)) {
                        const assocConfig = this.mailTemplateSalesChannelAssociationRepository.create(Shopware.Context.api);
                        assocConfig.mailTemplateId = this.mailTemplate.id;
                        assocConfig.mailTemplateTypeId = this.selectedType.id;
                        assocConfig.salesChannelId = salesChannelId;
                        this.mailTemplateSalesChannelsAssoc.add(assocConfig);
                    } else {
                        this.undeleteSaleschannel(salesChannelId);
                    }
                });
            }
        },

        mailTemplateHasSaleschannel(salesChannelId) {
            let found = false;
            this.mailTemplate.salesChannels.forEach((salesChannelAssoc) => {
                if (salesChannelAssoc.salesChannelId === salesChannelId) {
                    found = true;
                }
            });
            return found;
        },

        salesChannelIsSelected(salesChannelId) {
            // SalesChannel is selected in select field?
            return this.mailTemplateSalesChannels.has(salesChannelId);
        },

        undeleteSaleschannel(salesChannelId) {
            this.mailTemplate.salesChannels.forEach((salesChannelAssoc) => {
                if (salesChannelAssoc.salesChannelId === salesChannelId && salesChannelAssoc.isDeleted === true) {
                    salesChannelAssoc.isDeleted = false;
                }
            });
        }
    }
});
