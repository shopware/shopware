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

    inject: ['mailService', 'mediaService'],

    data() {
        return {
            mailTemplate: false,
            testerMail: '',
            mailTemplateId: null
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

        salesChannelAssociationStore() {
            return this.mailTemplate.getAssociation('salesChannels');
        },

        mailTemplateMediaStore() {
            return this.mailTemplate.getAssociation('media');
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

            this.$root.$on('sw-mail-template-media-form-open-sidebar', this.openMediaSidebar);
        },

        loadEntityData() {
            this.mailTemplate = this.mailTemplateStore.getById(this.mailTemplateId);

            this.mailTemplate.getAssociation('media').getList({
                page: 1,
                limit: 50
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

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        },

        onSave() {
            const mailTemplateSubject = this.mailTemplate.subject;
            const notificationSaveSuccess = {
                title: this.$tc('sw-mail-template.detail.titleSaveSuccess'),
                message: this.$tc(
                    'sw-mail-template.detail.messageSaveSuccess', 0, { subject: mailTemplateSubject }
                )
            };

            const notificationSaveError = {
                title: this.$tc('global.notification.notificationSaveErrorTitle'),
                message: this.$tc(
                    'global.notification.notificationSaveErrorMessage', 0, { subject: mailTemplateSubject }
                )
            };

            return this.mailTemplate.save().then(() => {
                this.$refs.mediaSidebarItem.getList();
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
                    this.mailService.testMailTemplateById(
                        this.testerMail,
                        this.mailTemplate,
                        salesChannel
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

        onAddMediaToMailTemplate(mediaItem) {
            if (this._checkIfMediaIsAlreadyUsed(mediaItem.id)) {
                this.createNotificationInfo({
                    message: this.$tc('sw-mail-template.list.errorMediaItemDuplicated')
                });
                return;
            }
            const mailTemplateMedia = this.mailTemplateMediaStore.create();
            mailTemplateMedia.mediaId = mediaItem.id;
            this.mailTemplate.media.push(mailTemplateMedia);
        },

        _checkIfMediaIsAlreadyUsed(mediaId) {
            return this.mailTemplate.media.some((mailTemplateMedia) => {
                return mailTemplateMedia.mediaId === mediaId;
            });
        }
    }
});
