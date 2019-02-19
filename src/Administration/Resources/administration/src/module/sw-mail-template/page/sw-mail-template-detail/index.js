import { Component, Mixin, State } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import template from './sw-mail-template-detail.html.twig';
import './sw-mail-template-detail.scss';

Component.register('sw-mail-template-detail', {
    template,

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification')
    ],

    inject: ['mailService'],

    data() {
        return {
            mailTemplate: false,
            testerMail: '',
            mailTemplateId: null
        };
    },

    computed: {
        mailTemplateStore() {
            return State.getStore('mail_template');
        },

        salesChannelStore() {
            return State.getStore('sales_channel');
        },

        salesChannelAssociationStore() {
            return this.mailTemplate.getAssociation('salesChannels');
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
            this.mailTemplate = this.mailTemplateStore.getById(this.mailTemplateId);
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

        onSave() {
            const mailTemplateName = this.mailTemplate.name;
            const notificationSaveSuccess = {
                title: this.$tc('sw-mail-template.detail.titleSaveSuccess'),
                message: this.$tc(
                    'sw-mail-template.detail.messageSaveSuccess', 0, { name: mailTemplateName }
                )
            };

            const notificationSaveError = {
                title: this.$tc('global.notification.notificationSaveErrorTitle'),
                message: this.$tc(
                    'global.notification.notificationSaveErrorMessage', 0, { entityName: mailTemplateName }
                )
            };

            return this.mailTemplate.save().then(() => {
                this.createNotificationSuccess(notificationSaveSuccess);
            }).catch((exception) => {
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

            if (this.mailTemplate.salesChannels.length) {
                this.mailTemplate.salesChannels.forEach((salesChannel) => {
                    return this.mailService.testMailTemplateById(
                        this.testerMail,
                        this.mailTemplate,
                        salesChannel.id
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
        }
    }
});
