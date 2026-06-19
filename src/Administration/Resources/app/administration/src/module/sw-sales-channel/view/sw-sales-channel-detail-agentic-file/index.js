/**
 * @sw-package discovery
 *
 * @private
 */

import template from './sw-sales-channel-detail-agentic-file.html.twig';
import './sw-sales-channel-detail-agentic-file.scss';

const { Mixin, Context, Defaults } = Shopware;
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
                    property: 'templateName',
                    label: this.$t('sw-sales-channel.detail.agenticFiles.detail.columnTemplate'),
                    primary: true,
                    allowResize: true,
                },
                {
                    property: 'role',
                    label: this.$t('sw-sales-channel.detail.agenticFiles.detail.columnSourceRole'),
                    allowResize: true,
                    width: '260px',
                },
            ];
        },

        contentSourceTemplates() {
            return (this.file?.templates ?? []).map((template) => {
                return {
                    ...template,
                    id: template.twigNamespace,
                };
            });
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
            if (!this.file || !this.salesChannel || !this.isEnabled(this.file) || !this.isStorefrontSalesChannel()) {
                return null;
            }

            const domainUrl = this.getSalesChannelDomainUrl();
            if (domainUrl) {
                return this.buildPublicUrl(domainUrl, this.file.fileName);
            }

            return null;
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

        onToggleEnabled() {
            if (!this.file || !this.salesChannel?.id) {
                return;
            }

            const configuration = this.ensureSalesChannelFileConfiguration();
            configuration.enabled = !this.isEnabled(this.file);
            configuration.templateOverrides = { ...(configuration.templateOverrides ?? {}) };
            this.file.configuration = configuration;
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

            if (this.templateOverrideDraft === this.selectedTemplateDefaultContent) {
                const hasChanged = this.removeTemplateOverride(this.selectedTemplate);

                this.closeTemplateOverrideModal();

                if (hasChanged) {
                    void this.loadPreview();
                }

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

            if (this.removeTemplateOverride(this.selectedTemplate)) {
                void this.loadPreview();
            }
        },

        removeTemplateOverride(template) {
            if (!this.findSalesChannelFileConfiguration() && !this.hasTemplateOverride(template)) {
                return false;
            }

            const configuration = this.ensureSalesChannelFileConfiguration();
            const templateOverrides = { ...(configuration.templateOverrides ?? {}) };

            if (!Object.hasOwn(templateOverrides, template.twigNamespace)) {
                return false;
            }

            delete templateOverrides[template.twigNamespace];
            configuration.templateOverrides = templateOverrides;

            this.writeConfigurationToSalesChannel(configuration);

            return true;
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

        getTemplateRoleVariant(template) {
            return template.role === 'base' ? 'neutral' : 'info';
        },

        getTemplateRoleLabel(template) {
            return template.role === 'base'
                ? this.$t('sw-sales-channel.detail.agenticFiles.detail.roleBase')
                : this.$t('sw-sales-channel.detail.agenticFiles.detail.roleExtension');
        },

        getSalesChannelDomainUrl() {
            const domains = this.salesChannel?.domains;

            if (!domains || domains.length === 0) {
                return null;
            }

            const adminLanguageId = Shopware.Store.get('session')?.languageId;
            const adminLanguageDomain = adminLanguageId
                ? domains.find((domain) => {
                      return domain.languageId === adminLanguageId;
                  })
                : null;

            if (adminLanguageDomain) {
                return adminLanguageDomain.url;
            }

            const systemLanguageDomain = domains.find((domain) => {
                return domain.languageId === Shopware.Defaults.systemLanguageId;
            });

            if (systemLanguageDomain) {
                return systemLanguageDomain.url;
            }

            if (typeof domains.first === 'function') {
                return domains.first()?.url ?? null;
            }

            return domains[0]?.url ?? null;
        },

        isStorefrontSalesChannel() {
            return this.salesChannel?.typeId === Defaults.storefrontSalesChannelTypeId;
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
