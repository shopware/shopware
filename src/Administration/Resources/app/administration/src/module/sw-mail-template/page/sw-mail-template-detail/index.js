import { dom } from 'src/core/service/util.service';
import template from './sw-mail-template-detail.html.twig';
import './sw-mail-template-detail.scss';

const { Mixin } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;
const { warn } = Shopware.Utils.debug;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['mailService', 'entityMappingService', 'repositoryFactory', 'acl', 'feature'],

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.allowSave;
            },
            method: 'onSave',
        },
        ESCAPE: 'onCancel',
    },

    data() {
        return {
            mailTemplate: false,
            testerMail: '',
            mailTemplateId: null,
            mailPreview: null,
            isLoading: false,
            isSaveSuccessful: false,
            mailTemplateType: {},
            selectedType: {},
            editorConfig: {
                enableBasicAutocompletion: true,
            },
            mailTemplateMedia: null,
            mailTemplateMediaSelected: {},
            fileAccept: 'application/pdf, image/*',
            testMailSalesChannelId: null,
            availableVariables: {},
            entitySchema: Object.fromEntries(Shopware.EntityDefinition.getDefinitionRegistry()),
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        ...mapPropertyErrors('mailTemplate', [
            'mailTemplateTypeId',
            'subject',
        ]),

        loadedAvailableVariables() {
            if (!this.mailTemplateType || !this.mailTemplateType.templateData) {
                return [];
            }
            if (Object.values(this.availableVariables).length === 0) {
                this.loadInitialAvailableVariables();
            }
            return Object.values(this.availableVariables);
        },

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
                        entityMappingService.getEntityMapping(prefix, innerMailTemplateType.availableEntities),
                    ).forEach((val) => {
                        properties.push({
                            value: val,
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
                (this.mailTemplate.subject || this.mailTemplate.translated?.subject) &&
                (this.mailTemplate.contentPlain || this.mailTemplate.translated?.contentPlain) &&
                (this.mailTemplate.contentHtml || this.mailTemplate.translated?.contentHtml) &&
                (this.mailTemplate.senderName || this.mailTemplate.translated?.senderName);
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
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        showPreview() {
            if (
                this.mailTemplate.contentHtml === undefined ||
                this.mailTemplate.mailTemplateTypeId === undefined ||
                this.mailTemplate.contentHtml === ''
            ) {
                return true;
            }
            return false;
        },

        hasTemplateData() {
            return Object.keys(this.mailTemplateType?.templateData || {}).length > 0;
        },
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Shopware.ExtensionAPI.publishData({
                id: 'sw-mail-template-detail__mailTemplate',
                path: 'mailTemplate',
                scope: this,
            });
            Shopware.ExtensionAPI.publishData({
                id: 'sw-mail-template-detail__mailTemplateMedia',
                path: 'mailTemplateMedia',
                scope: this,
            });
            Shopware.ExtensionAPI.publishData({
                id: 'sw-mail-template-detail__mailTemplateMediaSelected',
                path: 'mailTemplateMediaSelected',
                scope: this,
            });
            Shopware.ExtensionAPI.publishData({
                id: 'sw-mail-template-detail__mailTemplateType',
                path: 'mailTemplateType',
                scope: this,
            });
            Shopware.ExtensionAPI.publishData({
                id: 'sw-mail-template-detail__availableVariables',
                path: 'availableVariables',
                scope: this,
            });
            Shopware.ExtensionAPI.publishData({
                id: 'sw-mail-template-detail__testMailSalesChannelId',
                path: 'testMailSalesChannelId',
                scope: this,
            });
            Shopware.ExtensionAPI.publishData({
                id: 'sw-mail-template-detail__testerMail',
                path: 'testerMail',
                scope: this,
            });
            if (this.$route.params.id) {
                this.mailTemplateId = this.$route.params.id;
                this.loadEntityData();
            }
        },

        loadEntityData() {
            const criteria = new Criteria(1, 25);

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
            if (!this.mailTemplate.mailTemplateTypeId) {
                return Promise.resolve();
            }

            return this.mailTemplateTypeRepository.get(this.mailTemplate.mailTemplateTypeId).then((item) => {
                this.mailTemplateType = item;

                this.$refs.htmlEditor.defineAutocompletion(this.outerCompleterFunction);
                this.$refs.plainEditor.defineAutocompletion(this.outerCompleterFunction);
            });
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

        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
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

            updatePromises.push(this.mailTemplateRepository.save(this.mailTemplate).then(() => {
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
                        { subject: mailTemplateSubject },
                    ) + errormsg,
                });
            }));

            return Promise.all(updatePromises);
        },

        onClickTestMailTemplate() {
            const notificationTestMailSuccess = {
                message: this.$tc('sw-mail-template.general.notificationTestMailSuccessMessage'),
            };

            const notificationTestMailError = {
                message: this.$tc('sw-mail-template.general.notificationTestMailErrorMessage'),
            };

            const notificationTestMailErrorSalesChannel = {
                message: this.$tc('sw-mail-template.general.notificationTestMailSalesChannelErrorMessage'),
            };

            if (!this.testMailSalesChannelId) {
                this.createNotificationError(notificationTestMailErrorSalesChannel);
                return;
            }

            this.mailService.testMailTemplate(
                this.testerMail,
                this.mailPreviewContent(),
                this.mailTemplateMedia,
                this.testMailSalesChannelId,
            ).then((response) => {
                // Size is the length of the mail message, if the size is zero then no mail was sent
                const isMailSent = response?.size !== 0;
                if (!isMailSent) {
                    this.createNotificationError({
                        message: this.$tc('sw-mail-template.general.notificationGeneralSyntaxValidationErrorMessage'),
                    });
                    return;
                }

                this.createNotificationSuccess(notificationTestMailSuccess);
            }).catch((exception) => {
                this.createNotificationError(notificationTestMailError);
                warn(this._name, exception.message, exception.response);
            });
        },

        onClickShowPreview() {
            this.isLoading = true;

            this.mailPreview = this.mailService.buildRenderPreview(
                this.mailTemplateType,
                this.mailPreviewContent(),
            ).then((response) => {
                this.mailPreview = response;
            }).catch((error) => {
                this.mailPreview = null;
                if (!error.response?.data?.errors?.[0]?.detail) {
                    this.createNotificationError({
                        message: this.$tc('sw-mail-template.general.notificationGeneralSyntaxValidationErrorMessage'),
                    });
                } else {
                    this.createNotificationError({
                        message: this.$tc(
                            'sw-mail-template.general.notificationSyntaxValidationErrorMessage',
                            0,
                            { errorMsg: error.response?.data?.errors?.[0]?.detail },
                        ),
                    });
                }
            }).finally(() => {
                this.isLoading = false;
            });
        },

        mailPreviewContent() {
            const mailTemplate = { ...this.mailTemplate };

            if (mailTemplate.contentHtml) {
                mailTemplate.contentHtml = this.replaceContent(mailTemplate.contentHtml);
            }

            if (mailTemplate.translated?.contentHtml) {
                mailTemplate.translated.contentHtml = this.replaceContent(mailTemplate.translated.contentHtml);
            }

            if (mailTemplate.contentPlain) {
                mailTemplate.contentPlain = this.replaceContent(mailTemplate.contentPlain);
            }

            if (mailTemplate.translated?.contentPlain) {
                mailTemplate.translated.contentPlain = this.replaceContent(mailTemplate.translated.contentPlain);
            }

            return mailTemplate;
        },

        replaceContent(string) {
            // Replace .at([index]), first -> `.[index]` to suitable with mail template data
            return string.replace(/\.at\(([0-9]*)\)\./g, (matchs) => {
                const index = parseInt(matchs.match(/[0-9]/g).join(''), 10);
                return `.${index}.`;
            }).replace(/\.first\./g, '.0.');
        },

        onCancelShowPreview() {
            this.mailPreview = null;
        },

        onCopyVariable(variable) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(variable).catch((error) => {
                    let errormsg = '';
                    if (error.response.data.errors.length > 0) {
                        const errorDetailMsg = error.response.data.errors[0].detail;
                        errormsg = `<br/> ${this.$tc('sw-mail-template.detail.textErrorMessage')}: "${errorDetailMsg}"`;
                    }

                    this.createNotificationError({
                        message: errormsg,
                    });
                });

                return;
            }

            // non-https polyfill
            dom.copyToClipboard(variable);
        },

        async onChangeType(id) {
            if (!id) {
                this.selectedType = {};
                return;
            }
            this.isLoading = true;

            try {
                await this.getMailTemplateType();
                this.selectedType = await this.mailTemplateTypeRepository.get(id);
                this.loadInitialAvailableVariables();
                this.outerCompleterFunction();
            } catch (e) {
                let errormsg = '';
                if (e.response.data.errors.length > 0) {
                    const errorDetailMsg = e.response.data.errors[0].detail;
                    errormsg = `<br/> ${this.$tc('sw-mail-template.detail.textErrorMessage')}: "${errorDetailMsg}"`;
                }

                this.createNotificationError({
                    message: errormsg,
                });
            } finally {
                this.isLoading = false;
            }
        },

        getMediaColumns() {
            return [{
                property: 'fileName',
                label: 'sw-mail-template.list.columnFilename',
            }];
        },

        successfulUpload({ targetId }) {
            if (this.mailTemplate.media.find((mailTemplateMedia) => mailTemplateMedia.mediaId === targetId)) {
                return;
            }

            this.mediaRepository.get(targetId).then((mediaItem) => {
                this.createMailTemplateMediaAssoc(mediaItem);
            });
        },

        onMediaDrop(media) {
            this.successfulUpload({ targetId: media.id });
        },

        createMailTemplateMediaAssoc(mediaItem) {
            const mailTemplateMedia = this.mailTemplateMediaRepository.create();
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
                    message: this.$tc('sw-mail-template.list.errorMediaItemDuplicated'),
                });
                return false;
            }

            this.createMailTemplateMediaAssoc(mediaItem);
            return true;
        },

        loadAvailableVariables(variable, variableEntitySchema) {
            if (!this.mailTemplateType || !this.mailTemplateType.availableEntities) {
                return [];
            }

            const variablePath = variable.concat('.');
            const variableEntitySchemaPath = variableEntitySchema.concat('.');

            const foundVariables = Object.keys(Shopware.Utils.get(this.mailTemplateType.templateData, variable));

            const keys = foundVariables.map((val) => {
                const availableVariable = Shopware.Utils.get(this.mailTemplateType.templateData, variablePath.concat(val));
                const isObject = typeof availableVariable === 'object' && availableVariable !== null;
                const length = isObject ? Object.values(availableVariable).length : 0;

                // the pattern for schema is `.at(0)` or `.at(1)` instead of `.0` or `.1`
                const schema = this.isToManyAssociationVariable(variable) ?
                    `${variableEntitySchemaPath}at(${parseInt(val, 10)})` :
                    variableEntitySchemaPath + val;

                return {
                    id: variablePath + val,
                    schema,
                    name: val,
                    childCount: length,
                    parentId: variable,
                    afterId: null,
                };
            });


            this.addVariables(keys);

            return true;
        },

        isToManyAssociationVariable(variable) {
            if (!variable) {
                return false;
            }

            const variables = variable.split('.');
            variables.splice(1, 0, 'properties');
            const field = Shopware.Utils.get(this.entitySchema, `${variables.join('.')}`);

            return field && field.type === 'association' && ['one_to_many', 'many_to_many'].includes(field.relation);
        },

        onGetTreeItems(parent, schema) {
            this.loadAvailableVariables(parent, schema);
        },

        addVariables(variables) {
            variables.forEach((variable) => {
                this.$set(this.availableVariables, variable.id, variable);
            });
        },

        loadInitialAvailableVariables() {
            this.availableVariables = {};

            if (!this.hasTemplateData) {
                return;
            }

            Object.keys(this.mailTemplateType.templateData).forEach(variable => {
                const availableVariable = Shopware.Utils.get(this.mailTemplateType.templateData, variable);
                let length = 0;
                if (typeof availableVariable === 'object' && availableVariable !== null) {
                    length = Object.values(availableVariable).length;
                }

                this.addVariables([{
                    id: variable,
                    schema: variable,
                    name: variable,
                    childCount: length,
                    parentId: null,
                    afterId: null,
                }]);
            });
        },
    },
};
