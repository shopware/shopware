import { Application, Component, Mixin, State } from 'src/core/shopware';
import LocalStore from 'src/core/data/LocalStore';
import { warn } from 'src/core/service/utils/debug.utils';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-mail-template-detail.html.twig';

import './sw-mail-template-detail.scss';

Component.register('sw-mail-template-detail', {
    template,

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification')
    ],

    inject: ['mailService', 'entityMappingService', 'repositoryFactory', 'context'],

    data() {
        return {
            mailTemplate: false,
            testerMail: '',
            mailTemplateId: null,
            isLoading: false,
            isSaveSuccessful: false,
            eventAssociationStore: {},
            mailTemplateSalesChannelsStore: {},
            mailTemplateSalesChannels: [],
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
            // ToDo: If 'mailType' is translatable, please update:
            // ToDo: return this.placeholder(this.mailTemplate, 'mailType');
            return this.mailTemplate ? this.mailTemplate.mailType : '';
        },

        mailTemplateStore() {
            return State.getStore('mail_template');
        },

        salesChannelStore() {
            return State.getStore('sales_channel');
        },

        mailTemplateSalesChannelAssociationStore() {
            return this.mailTemplate.getAssociation('mailTemplateSalesChannels');
        },

        completerFunction() {
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

        mailTemplateTypeStore() {
            return State.getStore('mail_template_type');
        },

        mailTemplateType() {
            if (this.mailTemplate.mailTemplateTypeId) {
                return this.mailTemplateTypeStore.getById(
                    this.mailTemplate.mailTemplateTypeId
                );
            }
            return {};
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
            this.mailTemplateSalesChannelsStore = new LocalStore();
            const initContainer = Application.getContainer('init');
            const httpClient = initContainer.httpClient;

            this.eventAssociationStore = new LocalStore();

            httpClient.get('_info/business-events.json').then((response) => {
                Object.keys(response.data.events).forEach((eventName) => {
                    this.eventAssociationStore.add(eventName);
                });
            });
        },

        loadEntityData() {
            this.salesChannelStore.getList().then((response) => {
                this.salesChannels = response;
            });
            this.mailTemplateStore.getByIdAsync(this.mailTemplateId).then((response) => {
                this.mailTemplate = response;
                this.onChangeType(this.mailTemplate.mailTemplateTypeId);
                this.mailTemplateSalesChannelAssociationStore.getList({
                    associations: { salesChannel: {} }
                }).then((responseAssoc) => {
                    this.mailTemplateSalesChannelsAssoc = responseAssoc;
                    this.mailTemplateSalesChannelsAssoc.items.forEach((salesChannelAssoc) => {
                        if (salesChannelAssoc.salesChannelId !== null) {
                            this.mailTemplateSalesChannelsStore.add(salesChannelAssoc.salesChannel);
                        }
                    });
                    this.$refs.mailTemplateSalesChannel.loadSelected(true);
                    this.$refs.mailTemplateSalesChannel.updateValue();
                });
            });
        },

        abortOnLanguageChange() {
            return this.mailTemplate.hasChanges();
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
            const mailTemplateSubject = this.mailTemplate.subject || this.placeholder(this.mailTemplate, 'subject');

            const notificationSaveError = {
                title: this.$tc('global.notification.notificationSaveErrorTitle'),
                message: this.$tc(
                    'global.notification.notificationSaveErrorMessage', 0, { subject: mailTemplateSubject }
                )
            };
            this.isSaveSuccessful = false;
            this.isLoading = true;
            this.onChangeSalesChannel();
            return this.mailTemplate.save().then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch((exception) => {
                this.isLoading = false;
                this.createNotificationError(notificationSaveError);
                warn(this._name, exception.message, exception.response);
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

            if (this.mailTemplate.mailTemplateSalesChannels.length) {
                this.mailTemplate.mailTemplateSalesChannels.forEach((salesChannel) => {
                    let salesChannelId = '';
                    if (typeof salesChannel === 'object') {
                        salesChannelId = salesChannel.id;
                    } else {
                        salesChannelId = salesChannel;
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
            this.selectedType = this.mailTemplateTypeStore.getById(id);
            const mailTemplateSalesChannels = this.repositoryFactory.create('mail_template_sales_channel');
            const mailTemplateSalesChannelCriteria = new Criteria();
            mailTemplateSalesChannelCriteria.addFilter(
                Criteria.equals('mailTemplateTypeId', id)
            );
            mailTemplateSalesChannels.search(mailTemplateSalesChannelCriteria, this.context).then(
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
            this.completerFunction();
        },
        getPossibleSalesChannels(assignedSalesChannelIds) {
            this.setSalesChannelCriteria(assignedSalesChannelIds);
        },
        setSalesChannelCriteria(assignedSalesChannelIds) {
            this.salesChannelTypeCriteria = null;
            if (assignedSalesChannelIds.length > 0) {
                // get all salesChannels which are not assigned to this mailTemplateType
                // and all SalesChannels already assigned to the current mailTemplate if type not changed
                if (this.mailTemplate.mailTemplateTypeId === this.selectedType.id) {
                    this.salesChannelTypeCriteria = CriteriaFactory.multi('OR',
                        CriteriaFactory.equals('mailTemplatesSalesChannels.id', null),
                        CriteriaFactory.not(
                            'AND',
                            CriteriaFactory.equalsAny('id', assignedSalesChannelIds)
                        ),
                        CriteriaFactory.equals(
                            'mailTemplatesSalesChannels.mailTemplate.id', this.mailTemplate.id
                        ));
                } else { // type changed so only get free saleschannels
                    this.salesChannelTypeCriteria = CriteriaFactory.multi('OR',
                        CriteriaFactory.equals('mailTemplatesSalesChannels.id', null),
                        CriteriaFactory.not(
                            'AND',
                            CriteriaFactory.equalsAny('id', assignedSalesChannelIds)
                        ));
                }
            }
        },
        enrichAssocStores(responseAssoc) {
            this.mailTemplateSalesChannels = [];
            this.mailTemplateSalesChannelsAssoc = responseAssoc;
            this.mailTemplateSalesChannelsAssoc.items.forEach((salesChannelAssoc) => {
                if (salesChannelAssoc.salesChannelId !== null) {
                    this.mailTemplateSalesChannelsStore.add(salesChannelAssoc.salesChannel);
                    this.mailTemplateSalesChannels.push(salesChannelAssoc.salesChannel.id);
                }
            });
            if (this.$refs.mailTemplateSalesChannel) {
                this.$refs.mailTemplateSalesChannel.loadSelected(true);
            }
        },
        onChangeSalesChannel() {
            if (this.$refs.mailTemplateSalesChannel) {
                this.$refs.mailTemplateSalesChannel.updateValue();
            }
            if (Object.keys(this.mailTemplate).length === 0) {
                return;
            }
            // check selected saleschannels and associate to config
            if (this.mailTemplateSalesChannels && this.mailTemplateSalesChannels.length > 0) {
                this.mailTemplateSalesChannels.forEach((salesChannel) => {
                    if (!this.mailTemplateHasSaleschannel(salesChannel)) {
                        const assocConfig = this.mailTemplateSalesChannelAssociationStore.create();
                        assocConfig.mailTemplateId = this.mailTemplate.id;
                        assocConfig.mailTemplateTypeId = this.selectedType.id;
                        assocConfig.salesChannelId = salesChannel;
                    } else {
                        this.undeleteSaleschannel(salesChannel);
                    }
                });
            }
            this.mailTemplateSalesChannelAssociationStore.forEach((salesChannelAssoc) => {
                if (
                    typeof salesChannelAssoc.salesChannelId !== 'undefined' &&
                    !this.salesChannelIsSelected(salesChannelAssoc.salesChannelId)
                ) {
                    salesChannelAssoc.delete();
                }
            });
        },

        mailTemplateHasSaleschannel(salesChannelId) {
            let found = false;
            this.mailTemplateSalesChannelAssociationStore.forEach((salesChannelAssoc) => {
                if (salesChannelAssoc.salesChannelId === salesChannelId) {
                    found = true;
                }
            });
            return found;
        },

        salesChannelIsSelected(salesChannelId) {
            // SalesChannel is selected in select field?
            return (this.mailTemplateSalesChannels && this.mailTemplateSalesChannels.indexOf(salesChannelId) !== -1);
        },

        undeleteSaleschannel(salesChannelId) {
            this.mailTemplateSalesChannelAssociationStore.forEach((salesChannelAssoc) => {
                if (salesChannelAssoc.salesChannelId === salesChannelId && salesChannelAssoc.isDeleted === true) {
                    salesChannelAssoc.isDeleted = false;
                }
            });
        }
    }
});
