import { dom } from 'src/core/service/util.service';
import template from './sw-mail-template-detail.html.twig';
import './sw-mail-template-detail.scss';

const { Mixin, Context } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;
const { warn } = Shopware.Utils.debug;
const { camelCase } = Shopware.Utils.string;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

/**
 * @sw-package after-sales
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'mailService',
        'entityMappingService',
        'repositoryFactory',
        'acl',
        'feature',
        'businessEventService',
    ],

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
            showLanguageNotAssignedToSalesChannelWarning: false,
            triggerEvent: null,
            triggerEvents: [],
            validationErrors: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        ...mapPropertyErrors('mailTemplate', [
            'contentHtml',
            'contentPlain',
            'mailTemplateTypeId',
            'subject',
        ]),

        loadedAvailableVariables() {
            if (!this.triggerEvent) {
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

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
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
            })(this.entityMappingService, this.mailTemplateType);
        },

        mailTemplateTypeRepository() {
            return this.repositoryFactory.create('mail_template_type');
        },

        testMailRequirementsMet() {
            return (
                this.testerMail &&
                (this.mailTemplate.subject || this.mailTemplate.translated?.subject) &&
                (this.mailTemplate.contentPlain || this.mailTemplate.translated?.contentPlain) &&
                (this.mailTemplate.contentHtml || this.mailTemplate.translated?.contentHtml) &&
                (this.mailTemplate.senderName || this.mailTemplate.translated?.senderName)
            );
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
                    message: this.$t('sw-privileges.tooltip.warning'),
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

        lacksEmailSendPermission() {
            return !this.acl.can('api_send_email');
        },

        isSendButtonDisabled() {
            return this.isLoading || !this.testMailRequirementsMet || this.lacksEmailSendPermission;
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
                this.mailTemplateId = this.$route.params.id.toLowerCase();
                this.loadEntityData();
            }

            this.loadTriggerEvents();
        },

        getTriggerEventNameTranslated(eventName) {
            const eventNameCamelCase = camelCase(eventName);
            const translatedEventName = [
                `sw-flow-app.triggers-app.${eventNameCamelCase}`,
                `sw-flow-custom-event.event-tree.${eventNameCamelCase}`,
                `sw-flow.triggers.${eventNameCamelCase}`,
            ].find((key) => this.$te(key));

            return translatedEventName ? this.$t(translatedEventName) : eventName.replace(/_|-/g, ' ');
        },

        loadTriggerEvents() {
            this.businessEventService.getBusinessEvents().then((events) => {
                this.triggerEvents = events
                    .filter((event) => event.aware.includes('mailAware'))
                    .map((event) => ({
                        ...event,
                        label: event.name
                            .split('.')
                            .map((eventName) => this.getTriggerEventNameTranslated(eventName))
                            .join(' / '),
                        data: {
                            ...event.data,
                            salesChannel: {
                                nullable: true,
                                type: 'entity',
                                entityName: 'sales_channel',
                            },
                        },
                    }));
            });
        },

        loadEntityData() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('mailTemplateType');
            criteria.addAssociation('media.media');
            this.isLoading = true;
            this.mailTemplateRepository.get(this.mailTemplateId, Shopware.Context.api, criteria).then((item) => {
                this.mailTemplate = item;
                if (!this.mailTemplate.mailTemplateType?.id) {
                    this.isLoading = false;
                    this.createNotificationError({
                        message: this.$t('sw-mail-template.general.missingMailTemplateTypeErrorMessage'),
                    });
                } else {
                    this.onChangeType(this.mailTemplate.mailTemplateType.id);
                    this.getMailTemplateMedia();
                }
            });
        },

        getMailTemplateType() {
            if (!this.mailTemplate.mailTemplateTypeId) {
                return Promise.resolve();
            }

            return this.mailTemplateTypeRepository.get(this.mailTemplate.mailTemplateTypeId).then((item) => {
                this.mailTemplateType = item;

                // Not needed because the autocompletion method is passed as property to editor
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
            Shopware.Store.get('context').setApiLanguageId(languageId);
            this.loadEntityData();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onCancel() {
            this.$router.push({ name: 'sw.mail.template.index' });
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            const updatePromises = [];
            const mailTemplateSubject = this.mailTemplate.subject || this.placeholder(this.mailTemplate, 'subject');

            updatePromises.push(
                this.mailTemplateRepository
                    .save(this.mailTemplate)
                    .then(() => {
                        Promise.all(updatePromises).then(() => {
                            this.loadEntityData();
                            this.saveFinish();
                        });
                    })
                    .catch((error) => {
                        let errormsg = '';
                        this.isLoading = false;

                        if (error.response.data.errors.length > 0) {
                            const errorDetailMsg = error.response.data.errors[0].detail;
                            errormsg = `<br/> ${this.$t('sw-mail-template.detail.textErrorMessage')}: "${errorDetailMsg}"`;
                        }

                        this.createNotificationError({
                            message:
                                this.$t('sw-mail-template.detail.messageSaveError', { subject: mailTemplateSubject }, 0) +
                                errormsg,
                        });
                    }),
            );

            return Promise.all(updatePromises);
        },

        onClickTestMailTemplate() {
            const notificationTestMailSuccess = {
                message: this.$t('sw-mail-template.general.notificationTestMailSuccessMessage'),
            };

            const notificationTestMailError = {
                message: this.$t('sw-mail-template.general.notificationTestMailErrorMessage'),
            };

            const notificationTestMailErrorSalesChannel = {
                message: this.$t('sw-mail-template.general.notificationTestMailSalesChannelErrorMessage'),
            };

            if (!this.testMailSalesChannelId) {
                this.createNotificationError(notificationTestMailErrorSalesChannel);
                return;
            }

            const criteria = new Criteria();
            criteria.addAssociation('languages');

            this.salesChannelRepository.get(this.testMailSalesChannelId, Context.api, criteria).then((salesChannel) => {
                if (!salesChannel.languages.has(Shopware.Context.api.languageId)) {
                    this.showLanguageNotAssignedToSalesChannelWarning = true;

                    return;
                }

                this.showLanguageNotAssignedToSalesChannelWarning = false;
            });

            this.mailService
                .testMailTemplate(
                    this.testerMail,
                    this.mailPreviewContent(),
                    this.mailTemplateMedia,
                    this.testMailSalesChannelId,
                    this.mailTemplate.mailTemplateTypeId,
                    this.mailTemplate.id,
                )
                .then((response) => {
                    // Size is the length of the mail message, if the size is zero then no mail was sent
                    const isMailSent = response?.size !== 0;
                    if (!isMailSent) {
                        this.createNotificationError({
                            message: this.$t('sw-mail-template.general.notificationGeneralSyntaxValidationErrorMessage'),
                        });
                        return;
                    }

                    this.createNotificationSuccess(notificationTestMailSuccess);
                })
                .catch((exception) => {
                    this.createNotificationError(notificationTestMailError);
                    warn(this._name, exception.message, exception.response);
                });
        },

        onClickValidateMailTemplate() {
            this.mailService
                .validateMailTemplate(this.triggerEvent.class, {
                    subject: this.mailTemplate.subject,
                    senderName: this.mailTemplate.senderName,
                    contentPlain: this.mailTemplate.contentPlain,
                    contentHtml: this.mailTemplate.contentHtml,
                })
                .then((response) => {
                    this.validationErrors = response
                        .map((entry) => this.translateValidationError(entry))
                        .sort((a, b) => {
                            return (a.level === 'error' ? 1 : 0) - (b.level === 'error' ? 1 : 0);
                        });
                })
                .catch((exception) => {
                    this.createNotificationError(exception.message);
                });
        },

        translateValidationError(validationError) {
            let field;
            switch (validationError.field) {
                case 'subject':
                    field = 'options.labelSubject';
                    break;
                case 'senderName':
                    field = 'options.labelSenderName';
                    break;
                case 'contentHtml':
                    field = 'mailText.labelContentHtml';
                    break;
                case 'contentPlain':
                    field = 'mailText.labelContentPlain';
                    break;
                default:
                    break;
            }

            const content = {};
            switch (validationError.type) {
                case 'arrayAccess':
                case 'unknownVariable':
                case 'complexElement':
                    content.variable = validationError.variable;
                    break;
                case 'syntax':
                    content.message = validationError.message;
                    break;
                default:
                    break;
            }

            return {
                level: validationError.level,
                message: this.$t(`sw-mail-template.validation.${validationError.level}.${validationError.type}`, content),
                hint: this.$t(
                    'sw-mail-template.validation.hint',
                    { line: validationError.line, field: this.$t(`sw-mail-template.detail.${field}`) },
                    validationError.line === 0 ? 1 : 2,
                ),
                name: this.$t(`sw-mail-template.validation.${validationError.level}.levelName`),
            };
        },

        onTriggerEventChange(eventName) {
            this.triggerEvent = this.triggerEvents.find((event) => event.name === eventName);
        },

        onClickShowPreview() {
            this.isLoading = true;

            this.mailPreview = this.mailService
                .buildRenderPreview(this.mailTemplateType, this.mailPreviewContent())
                .then((response) => {
                    this.mailPreview = response;
                })
                .catch((error) => {
                    this.mailPreview = null;
                    if (!error.response?.data?.errors?.[0]?.detail) {
                        this.createNotificationError({
                            message: this.$t('sw-mail-template.general.notificationGeneralSyntaxValidationErrorMessage'),
                        });
                    } else {
                        this.createNotificationError({
                            message: this.$t(
                                'sw-mail-template.general.notificationSyntaxValidationErrorMessage',
                                {
                                    errorMsg: error.response?.data?.errors?.[0]?.detail,
                                },
                                0,
                            ),
                        });
                    }
                })
                .finally(() => {
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
            return string
                .replace(/\.at\(([0-9]*)\)\./g, (matchs) => {
                    const index = parseInt(matchs.match(/[0-9]/g).join(''), 10);
                    return `.${index}.`;
                })
                .replace(/\.first\./g, '.0.');
        },

        onCancelShowPreview() {
            this.mailPreview = null;
        },

        async onCopyVariable(variable) {
            try {
                await dom.copyStringToClipboard(variable);
            } catch (error) {
                let errormsg = '';
                if (error.response.data.errors.length > 0) {
                    const errorDetailMsg = error.response.data.errors[0].detail;
                    errormsg = `<br/> ${this.$t('sw-mail-template.detail.textErrorMessage')}: "${errorDetailMsg}"`;
                }

                this.createNotificationError({
                    message: errormsg,
                });
            }
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
                let errormsg = e.message ?? '';
                if (e.response?.data?.errors?.length > 0) {
                    const errorDetailMsg = e.response.data.errors[0].detail;
                    errormsg = `<br/> ${this.$t('sw-mail-template.detail.textErrorMessage')}: "${errorDetailMsg}"`;
                }

                this.createNotificationError({
                    message: errormsg,
                });
            } finally {
                this.isLoading = false;
            }
        },

        getMediaColumns() {
            return [
                {
                    property: 'fileName',
                    label: 'sw-mail-template.list.columnFilename',
                },
            ];
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
            const foundItem = this.mailTemplate.media.find(
                (mailTemplateMedia) => mailTemplateMedia.mediaId === mailTemplateMediaId,
            );
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
                return (
                    mailTemplateMedia.mediaId === mediaId && mailTemplateMedia.languageId === Shopware.Context.api.languageId
                );
            });
        },

        onAddItemToAttachment(mediaItem) {
            if (this._checkIfMediaIsAlreadyUsed(mediaItem.id)) {
                this.createNotificationInfo({
                    message: this.$t('sw-mail-template.list.errorMediaItemDuplicated'),
                });
                return false;
            }

            this.createMailTemplateMediaAssoc(mediaItem);
            return true;
        },

        loadAvailableVariables(variable, variableEntitySchema) {
            if (!this.triggerEvent) {
                return;
            }

            const variablePathParts = variable.split('.');

            let entityPath = '';

            let currentField = null;
            let skip = false;

            variablePathParts.forEach((variablePathPart) => {
                if (skip) {
                    return;
                }

                if (!currentField && !this.triggerEvent.data[variablePathPart]) {
                    skip = true;
                    return;
                }

                currentField = this.triggerEvent.data[variablePathPart];
                entityPath += `${variablePathPart}.`;

                if (currentField.type === 'entity') {
                    skip = true;
                }
            });

            if (!currentField) {
                return;
            }

            if (currentField.type === 'object') {
                this.addVariables(
                    Object.keys(currentField.data)
                        .sort()
                        .map((key) => ({
                            id: `${variable}.${key}`,
                            schema: variableEntitySchema,
                            name: key,
                            childCount: [
                                'object',
                                'entity',
                            ].includes(currentField.data[key])
                                ? 1
                                : 0,
                            parentId: variable,
                            afterId: null,
                        })),
                );

                return;
            }

            if (currentField.type === 'entity') {
                const mapping = [];
                mapping[currentField.entityName] = currentField.entityName;

                const entityMappingPath = variable.split(entityPath)[1]
                    ? `${currentField.entityName}.${variable.split(entityPath)[1]}.`
                    : `${currentField.entityName}.`;

                const entitySchema = this.entityMappingService.getEntityMapping(entityMappingPath, mapping);

                this.addVariables(
                    Object.keys(entitySchema)
                        .sort()
                        .map((key) => ({
                            id: `${variable}.${key}`,
                            schema: variableEntitySchema,
                            name: key,
                            childCount: entitySchema[key].type === 'association' ? 1 : 0,
                            parentId: variable,
                            afterId: null,
                        })),
                );
            }
        },

        onGetTreeItems(parent, schema) {
            this.loadAvailableVariables(parent, schema);
        },

        addVariables(variables) {
            variables.forEach((variable) => {
                this.availableVariables[variable.id] = variable;
            });
        },

        loadInitialAvailableVariables() {
            this.availableVariables = {};

            if (!this.triggerEvent) {
                return;
            }

            this.addVariables(
                Object.keys(this.triggerEvent.data)
                    .sort()
                    .map((variable) => ({
                        id: variable,
                        schema: variable,
                        name: variable,
                        childCount: [
                            'entity',
                            'object',
                        ].includes(this.triggerEvent.data[variable].type)
                            ? 1
                            : 0,
                        parentId: null,
                        afterId: null,
                    })),
            );
        },
    },
};
