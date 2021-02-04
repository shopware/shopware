import template from './sw-mail-template-detail.html.twig';
import './sw-mail-template-detail.scss';

const { Component, Mixin } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;
const { warn } = Shopware.Utils.debug;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-mail-template-detail', {
    template,

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification')
    ],

    inject: ['mailService', 'entityMappingService', 'repositoryFactory', 'acl', 'feature'],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.allowSave;
            },
            method: 'onSave'
        },
        ESCAPE: 'onCancel'
    },

    data() {
        return {
            mailTemplate: false,
            testerMail: '',
            mailTemplateId: null,
            isLoading: false,
            isSaveSuccessful: false,
            mailTemplateType: {},
            selectedType: {},
            editorConfig: {
                enableBasicAutocompletion: true
            },
            mailTemplateMedia: null,
            mailTemplateMediaSelected: {},
            fileAccept: 'application/pdf, image/*',
            testMailSalesChannelId: null
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        ...mapPropertyErrors('mailTemplate', [
            'mailTemplateTypeId',
            'subject'
        ]),

        identifier() {
            return this.placeholder(this.mailTemplateType, 'name');
        },

        mailTemplateRepository() {
            return this.repositoryFactory.create('mail_template');
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        mailTemplateMediaRepository() {
            return this.repositoryFactory.create('mail_template_media');
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
        },

        mediaColumns() {
            return this.getMediaColumns();
        },

        allowSave() {
            return this.mailTemplate && this.mailTemplate.isNew()
                ? this.acl.can('mail_templates.creator')
                : this.acl.can('mail_templates.editor');
        },

        tooltipSave() {
            if (!this.allowSave) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.allowSave,
                    showOnDisabledElements: true
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
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

            criteria.addAssociation('mailTemplateType');
            criteria.addAssociation('media.media');
            this.isLoading = true;
            this.mailTemplateRepository.get(this.mailTemplateId, Shopware.Context.api, criteria).then((item) => {
                this.mailTemplate = item;
                this.onChangeType(this.mailTemplate.mailTemplateType.id);
                this.getMailTemplateMedia();
            });
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

        createMediaCollection() {
            return new EntityCollection('/media', 'media', Shopware.Context.api);
        },

        getMailTemplateMedia() {
            this.mailTemplateMedia = this.createMediaCollection();

            this.mailTemplate.media.forEach((mediaAssoc) => {
                if (mediaAssoc.languageId === Shopware.Context.api.languageId) {
                    this.mailTemplateMedia.push(mediaAssoc.media);
                }
            });
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

        onCancel() {
            this.$router.push({ name: 'sw.mail.template.index' });
        },

        onSave() {
            const updatePromises = [];
            const mailTemplateSubject = this.mailTemplate.subject || this.placeholder(this.mailTemplate, 'subject');

            this.isSaveSuccessful = false;
            this.isLoading = true;

            updatePromises.push(this.mailTemplateRepository.save(this.mailTemplate, Shopware.Context.api).then(() => {
                Promise.all(updatePromises).then(() => {
                    this.loadEntityData();
                    this.saveFinish();
                });
            }).catch((error) => {
                let errormsg = '';
                this.isLoading = false;

                if (error.response.data.errors.length > 0) {
                    const errorDetailMsg = error.response.data.errors[0].detail;
                    errormsg = `<br/> ${this.$tc('sw-mail-template.detail.textErrorMessage')}: "${errorDetailMsg}"`;
                }

                this.createNotificationError({
                    message: this.$tc(
                        'sw-mail-template.detail.messageSaveError',
                        0,
                        { subject: mailTemplateSubject }
                    ) + errormsg
                });
            }));
        },

        onClickTestMailTemplate() {
            const notificationTestMailSuccess = {
                message: this.$tc('sw-mail-template.general.notificationTestMailSuccessMessage')
            };

            const notificationTestMailError = {
                message: this.$tc('sw-mail-template.general.notificationTestMailErrorMessage')
            };

            const notificationTestMailErrorSalesChannel = {
                message: this.$tc('sw-mail-template.general.notificationTestMailSalesChannelErrorMessage')
            };

            if (!this.testMailSalesChannelId) {
                this.createNotificationError(notificationTestMailErrorSalesChannel);
                return;
            }

            this.mailService.testMailTemplate(
                this.testerMail,
                this.mailTemplate,
                this.mailTemplateMedia,
                this.testMailSalesChannelId
            ).then(() => {
                this.createNotificationSuccess(notificationTestMailSuccess);
            }).catch((exception) => {
                this.createNotificationError(notificationTestMailError);
                warn(this._name, exception.message, exception.response);
            });
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

                this.isLoading = false;
            });
            this.outerCompleterFunction();
        },

        getMediaColumns() {
            return [{
                property: 'fileName',
                label: 'sw-mail-template.list.columnFilename'
            }];
        },

        successfulUpload({ targetId }) {
            if (this.mailTemplate.media.find((mailTemplateMedia) => mailTemplateMedia.mediaId === targetId)) {
                return;
            }

            this.mediaRepository.get(targetId, Shopware.Context.api).then((mediaItem) => {
                this.createMailTemplateMediaAssoc(mediaItem);
            });
        },

        onMediaDrop(media) {
            this.successfulUpload({ targetId: media.id });
        },

        createMailTemplateMediaAssoc(mediaItem) {
            const mailTemplateMedia = this.mailTemplateMediaRepository.create(Shopware.Context.api);
            mailTemplateMedia.mailTemplateId = this.mailTemplateId;
            mailTemplateMedia.languageId = Shopware.Context.api.languageId;
            mailTemplateMedia.mediaId = mediaItem.id;
            if (this.mailTemplate.media.length <= 0) {
                mailTemplateMedia.position = 0;
            } else {
                mailTemplateMedia.position = this.mailTemplate.media.length;
            }
            this.mailTemplate.media.push(mailTemplateMedia);
            this.mailTemplateMedia.push(mediaItem);
        },

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        },

        onDeleteMedia(mailTemplateMediaId) {
            const foundItem = this.mailTemplate.media
                .find((mailTemplateMedia) => mailTemplateMedia.mediaId === mailTemplateMediaId);
            if (foundItem) {
                this.mailTemplate.media.remove(foundItem.id);
                this.getMailTemplateMedia();
            }
        },

        onSelectionChanged(selection) {
            this.selectedItems = selection;
        },

        onDeleteSelectedMedia() {
            Object.keys(this.selectedItems).forEach((mailTemplateMediaId) => {
                this.onDeleteMedia(mailTemplateMediaId);
            });
        },

        _checkIfMediaIsAlreadyUsed(mediaId) {
            return this.mailTemplate.media.some((mailTemplateMedia) => {
                return mailTemplateMedia.mediaId === mediaId &&
                    mailTemplateMedia.languageId === Shopware.Context.api.languageId;
            });
        },

        onAddItemToAttachment(mediaItem) {
            if (this._checkIfMediaIsAlreadyUsed(mediaItem.id)) {
                this.createNotificationInfo({
                    message: this.$tc('sw-mail-template.list.errorMediaItemDuplicated')
                });
                return false;
            }

            this.createMailTemplateMediaAssoc(mediaItem);
            return true;
        }
    }
});
