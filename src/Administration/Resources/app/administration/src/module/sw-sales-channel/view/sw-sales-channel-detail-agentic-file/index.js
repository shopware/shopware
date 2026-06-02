/**
 * @sw-package discovery
 *
 * @private
 */

import template from './sw-sales-channel-detail-agentic-file.html.twig';
import './sw-sales-channel-detail-agentic-file.scss';

const { Mixin, Context } = Shopware;
const { EntityCollection } = Shopware.Data;

const FILE_FAMILY_AGENTIC = 'agentic';
const USER_PROVIDED_CONTENT_OVERRIDE_KEY = 'user_provided_content';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'salesChannelFileApiService',
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        salesChannel: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            file: null,
            preview: null,
            isLoading: false,
            isPreviewLoading: false,
            isSaving: false,
            isContentSourcesExpanded: false,
            customNotes: '',
            selectedTemplate: null,
            templateOverrideDraft: '',
        };
    },

    computed: {
        salesChannelFileRepository() {
            return this.repositoryFactory.create('sales_channel_file');
        },

        routeFileName() {
            const fileName = this.$route.params.fileName;

            if (Array.isArray(fileName)) {
                return fileName.join('/');
            }

            return fileName ?? '';
        },

        listRoute() {
            return {
                name: 'sw.sales.channel.detail.agenticFiles',
                params: {
                    id: this.$route.params.id,
                },
            };
        },

        templateColumns() {
            return [
                {
                    property: 'sourceName',
                    label: this.$t('sw-sales-channel.detail.agenticFiles.detail.columnTwigNamespace'),
                    primary: true,
                    allowResize: true,
                    width: '260px',
                },
                {
                    property: 'role',
                    label: this.$t('sw-sales-channel.detail.agenticFiles.detail.columnSourceRole'),
                    allowResize: true,
                    width: '150px',
                },
                {
                    property: 'override',
                    label: this.$t('sw-sales-channel.detail.agenticFiles.detail.columnOverride'),
                    allowResize: true,
                    width: '150px',
                },
            ];
        },

        templateOverrides() {
            return this.file?.configuration?.templateOverrides ?? {};
        },

        description() {
            if (!this.file) {
                return '';
            }

            return this.getDescription(this.file);
        },

        previewContent() {
            return this.preview?.content ?? '';
        },

        supportsCustomNotes() {
            return this.file?.supportsUserProvidedContent === true;
        },

        contentSourcesToggleIcon() {
            return this.isContentSourcesExpanded ? 'regular-chevron-up-xs' : 'regular-chevron-down-xs';
        },

        contentSourcesToggleLabel() {
            return this.isContentSourcesExpanded
                ? this.$t('sw-sales-channel.detail.agenticFiles.detail.hideContentSources')
                : this.$t('sw-sales-channel.detail.agenticFiles.detail.showContentSources');
        },

        selectedTemplateDefaultContent() {
            if (!this.selectedTemplate) {
                return '';
            }

            return this.getTemplateDefaultContent(this.selectedTemplate);
        },

        canResetTemplateOverride() {
            if (!this.selectedTemplate) {
                return false;
            }

            return (
                this.hasTemplateOverride(this.selectedTemplate) ||
                this.templateOverrideDraft !== this.selectedTemplateDefaultContent
            );
        },

        publicPreviewUrl() {
            if (!this.file || !this.salesChannel) {
                return null;
            }

            const domain = this.getFirstSalesChannelDomain();
            if (domain?.url) {
                return this.buildPublicUrl(domain.url, this.file.fileName);
            }

            if (!this.salesChannel.accessKey) {
                return null;
            }

            const url = new URL(this.buildPublicUrl(this.getInstallationUrl(), this.file.fileName));
            url.searchParams.set('sw-access-key', this.salesChannel.accessKey);

            return url.toString();
        },
    },

    watch: {
        salesChannel: {
            immediate: true,
            handler(newSalesChannel, previousSalesChannel) {
                if (!newSalesChannel?.id || newSalesChannel.id === previousSalesChannel?.id) {
                    return;
                }

                void this.loadFile();
            },
        },

        '$route.params.fileName'() {
            if (!this.salesChannel?.id) {
                return;
            }

            void this.loadFile();
        },

        customNotes(newValue) {
            if (this.isLoading || !this.file || !this.supportsCustomNotes) {
                return;
            }

            this.writeCustomNotesToSalesChannelFile(newValue);
        },
    },

    methods: {
        async loadFile() {
            if (!this.salesChannel?.id) {
                return;
            }

            this.isLoading = true;
            this.file = null;
            this.preview = null;
            this.customNotes = '';
            this.closeTemplateOverrideModal();

            try {
                const response = await this.salesChannelFileApiService.detail(
                    FILE_FAMILY_AGENTIC,
                    this.salesChannel.id,
                    this.routeFileName,
                );
                this.file = response?.data ?? null;

                if (this.file) {
                    this.file.configuration = this.findSalesChannelFileConfiguration() ?? this.file.configuration;
                    this.customNotes = this.getUserProvidedContent(this.file);

                    await this.loadPreview();
                }
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-sales-channel.detail.agenticFiles.messageLoadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        async loadPreview() {
            if (!this.file || !this.salesChannel?.id) {
                return;
            }

            this.isPreviewLoading = true;

            try {
                this.preview = await this.salesChannelFileApiService.preview(
                    this.file.fileFamily,
                    this.salesChannel.id,
                    this.file.fileName,
                    this.templateOverrides,
                );
            } catch {
                this.preview = null;
                this.createNotificationError({
                    message: this.$t('sw-sales-channel.detail.agenticFiles.detail.messagePreviewError'),
                });
            } finally {
                this.isPreviewLoading = false;
            }
        },

        async onToggleEnabled() {
            if (!this.file || !this.salesChannel?.id || this.isSaving) {
                return;
            }

            const enabled = !this.isEnabled(this.file);
            this.isSaving = true;

            try {
                this.file.configuration = await this.salesChannelFileApiService.saveConfiguration(
                    this.file,
                    this.salesChannel.id,
                    enabled,
                );
                this.writeConfigurationToSalesChannel(this.file.configuration);
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-sales-channel.detail.agenticFiles.messageSaveError'),
                });
            } finally {
                this.isSaving = false;
            }
        },

        isEnabled(file) {
            return file.configuration?.enabled === true;
        },

        hasTemplateOverride(template) {
            return Object.hasOwn(this.templateOverrides, template.twigNamespace);
        },

        openTemplateOverrideModal(template) {
            this.selectedTemplate = template;
            this.templateOverrideDraft = this.getTemplateOverrideContent(template);
        },

        closeTemplateOverrideModal() {
            this.selectedTemplate = null;
            this.templateOverrideDraft = '';
        },

        applyTemplateOverride() {
            if (!this.selectedTemplate || !this.file) {
                return;
            }

            const configuration = this.ensureSalesChannelFileConfiguration();
            const templateOverrides = { ...(configuration.templateOverrides ?? {}) };

            templateOverrides[this.selectedTemplate.twigNamespace] = this.templateOverrideDraft;
            configuration.templateOverrides = templateOverrides;

            this.writeConfigurationToSalesChannel(configuration);
            this.closeTemplateOverrideModal();
            void this.loadPreview();
        },

        resetTemplateOverride() {
            if (!this.selectedTemplate || !this.file) {
                return;
            }

            this.templateOverrideDraft = this.selectedTemplateDefaultContent;

            if (!this.findSalesChannelFileConfiguration() && !this.hasTemplateOverride(this.selectedTemplate)) {
                return;
            }

            const configuration = this.ensureSalesChannelFileConfiguration();
            const templateOverrides = { ...(configuration.templateOverrides ?? {}) };

            delete templateOverrides[this.selectedTemplate.twigNamespace];
            configuration.templateOverrides = templateOverrides;

            this.writeConfigurationToSalesChannel(configuration);
            void this.loadPreview();
        },

        getTemplateOverrideContent(template) {
            const override = this.templateOverrides[template.twigNamespace];

            return typeof override === 'string' ? override : this.getTemplateDefaultContent(template);
        },

        getTemplateDefaultContent(template) {
            return typeof template.templateContent === 'string' ? template.templateContent : '';
        },

        getUserProvidedContent(file) {
            const userProvidedContent = file.configuration?.templateOverrides?.[USER_PROVIDED_CONTENT_OVERRIDE_KEY];

            return typeof userProvidedContent === 'string' ? userProvidedContent : '';
        },

        writeCustomNotesToSalesChannelFile(customNotes) {
            if (customNotes.trim() === '' && !this.findSalesChannelFileConfiguration()) {
                return;
            }

            const configuration = this.ensureSalesChannelFileConfiguration();
            const templateOverrides = { ...(configuration.templateOverrides ?? {}) };

            if (customNotes.trim() === '') {
                delete templateOverrides[USER_PROVIDED_CONTENT_OVERRIDE_KEY];
            } else {
                templateOverrides[USER_PROVIDED_CONTENT_OVERRIDE_KEY] = customNotes;
            }

            configuration.templateOverrides = templateOverrides;
            this.file.configuration = configuration;
        },

        ensureSalesChannelFileConfiguration() {
            const existingConfiguration = this.findSalesChannelFileConfiguration();
            if (existingConfiguration) {
                return existingConfiguration;
            }

            const configuration = this.salesChannelFileRepository.create(Context.api);
            if (this.file.configuration?.id) {
                configuration.id = this.file.configuration.id;
            }
            configuration.salesChannelId = this.salesChannel.id;
            configuration.fileFamily = this.file.fileFamily;
            configuration.fileName = this.file.fileName;
            configuration.enabled = this.file.configuration?.enabled ?? this.isEnabled(this.file);
            configuration.templateOverrides = { ...(this.file.configuration?.templateOverrides ?? {}) };

            this.ensureSalesChannelFileCollection().add(configuration);

            return configuration;
        },

        findSalesChannelFileConfiguration() {
            const salesChannelFiles = this.salesChannel?.salesChannelFiles;
            if (!salesChannelFiles) {
                return null;
            }

            return (
                salesChannelFiles.find((configuration) => {
                    return (
                        configuration.fileFamily === this.file?.fileFamily && configuration.fileName === this.file?.fileName
                    );
                }) ?? null
            );
        },

        writeConfigurationToSalesChannel(configuration) {
            const salesChannelConfiguration = this.ensureSalesChannelFileConfiguration();

            salesChannelConfiguration.id = configuration.id;
            salesChannelConfiguration.salesChannelId = this.salesChannel.id;
            salesChannelConfiguration.fileFamily = this.file.fileFamily;
            salesChannelConfiguration.fileName = this.file.fileName;
            salesChannelConfiguration.enabled = configuration.enabled;
            salesChannelConfiguration.templateOverrides = configuration.templateOverrides ?? {};
            this.file.configuration = salesChannelConfiguration;
        },

        ensureSalesChannelFileCollection() {
            if (!this.salesChannel.salesChannelFiles) {
                this.salesChannel.salesChannelFiles = new EntityCollection(
                    `/sales-channel/${this.salesChannel.id}/salesChannelFiles`,
                    'sales_channel_file',
                    Context.api,
                    null,
                );
            }

            return this.salesChannel.salesChannelFiles;
        },

        getDisplayFileName(file) {
            return file.fileName.split('/').pop();
        },

        getPublicPath(file) {
            return `/${file.fileName}`;
        },

        getToggleLabel(file) {
            return this.isEnabled(file)
                ? this.$t('sw-sales-channel.detail.agenticFiles.actionDisable')
                : this.$t('sw-sales-channel.detail.agenticFiles.actionEnable');
        },

        getDescription(file) {
            const snippetKey = this.getDescriptionSnippetKey(file);

            return this.$te(snippetKey) ? this.$t(snippetKey) : '';
        },

        getDescriptionSnippetKey(file) {
            const fileFamily = this.formatSnippetPathSegment(file.fileFamily);
            const fileName = this.formatSnippetPathSegment(file.fileName);

            return `sw-sales-channel.detail.agenticFiles.descriptions${fileFamily}${fileName}`;
        },

        formatSnippetPathSegment(segment) {
            return `[${JSON.stringify(segment)}]`;
        },

        getEnabledVariant(file) {
            return this.isEnabled(file) ? 'success' : 'neutral';
        },

        getEnabledLabel(file) {
            return this.isEnabled(file)
                ? this.$t('sw-sales-channel.detail.agenticFiles.enabledState.enabled')
                : this.$t('sw-sales-channel.detail.agenticFiles.enabledState.disabled');
        },

        getOverrideVariant(template) {
            return this.hasTemplateOverride(template) ? 'info' : 'neutral';
        },

        getOverrideLabel(template) {
            return this.hasTemplateOverride(template)
                ? this.$t('sw-sales-channel.detail.agenticFiles.detail.overrideConfigured')
                : this.$t('sw-sales-channel.detail.agenticFiles.detail.overrideDefault');
        },

        getSourceIconSrc(template) {
            if (template.sourceIcon) {
                return `data:image/png;base64, ${template.sourceIcon}`;
            }

            return null;
        },

        isShopwareSource(template) {
            return template.sourceType === 'shopware';
        },

        getSourceFallbackIcon(template) {
            if (template.sourceType === 'app' || template.sourceType === 'plugin') {
                return 'regular-plug';
            }

            return 'regular-code';
        },

        getTemplateRoleVariant(template) {
            return template.role === 'base' ? 'neutral' : 'info';
        },

        getTemplateRoleLabel(template) {
            return template.role === 'base'
                ? this.$t('sw-sales-channel.detail.agenticFiles.detail.roleBase')
                : this.$t('sw-sales-channel.detail.agenticFiles.detail.roleExtension');
        },

        getFirstSalesChannelDomain() {
            const domains = this.salesChannel?.domains;

            if (!domains || domains.length === 0) {
                return null;
            }

            if (typeof domains.first === 'function') {
                return domains.first();
            }

            return domains[0] ?? null;
        },

        getInstallationUrl() {
            const installationPath = Shopware.Context.api.installationPath;

            if (!installationPath) {
                return window.location.origin;
            }

            if (installationPath.startsWith('http://') || installationPath.startsWith('https://')) {
                return installationPath;
            }

            if (installationPath.startsWith('/')) {
                return `${window.location.origin}${installationPath}`;
            }

            return window.location.origin;
        },

        buildPublicUrl(baseUrl, fileName) {
            const normalizedBaseUrl = baseUrl.replace(/\/+$/g, '');
            const encodedFileName = fileName
                .split('/')
                .map((segment) => encodeURIComponent(segment))
                .join('/');

            return `${normalizedBaseUrl}/${encodedFileName}`;
        },
    },
};
