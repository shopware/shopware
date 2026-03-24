/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-detail-product-comparison.html.twig';
import './sw-sales-channel-detail-product-comparison.scss';

const { Mixin, Defaults } = Shopware;
const { Criteria } = Shopware.Data;
const { warn } = Shopware.Utils.debug;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'salesChannelService',
        'repositoryFactory',
        'productExportService',
        'entityMappingService',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    props: {
        salesChannel: {
            required: true,
        },

        productExport: {
            required: true,
        },

        isLoading: {
            type: Boolean,
            default: false,
        },
    },

    data() {
        return {
            showDeleteModal: false,
            defaultSnippetSetId: '71a916e745114d72abafbfdc51cbd9d0',
            isLoadingDomains: false,
            deleteDomain: null,
            previewContent: null,
            previewErrors: null,
            isLoadingPreview: false,
            isPreviewSuccessful: false,
            isLoadingValidate: false,
            isValidateSuccessful: false,
            isLoadingReset: false,
        };
    },

    computed: {
        isAgenticAi() {
            return this.salesChannel?.typeId === Defaults.agenticAiTypeId;
        },

        editorConfig() {
            return {
                enableBasicAutocompletion: true,
            };
        },

        productExportRepository() {
            return this.repositoryFactory.create('product_export');
        },

        domainRepository() {
            return this.repositoryFactory.create(this.salesChannel.domains.entity, this.salesChannel.domains.source);
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        mainNavigationCriteria() {
            const criteria = new Criteria(1, 10);

            return criteria.addFilter(Criteria.equals('type', 'page'));
        },

        outerCompleterFunctionHeader() {
            return this.outerCompleterFunction({
                productExport: 'product_export',
            });
        },

        outerCompleterFunctionBody() {
            return this.outerCompleterFunction({
                productExport: 'product_export',
                product: 'product',
            });
        },

        outerCompleterFunctionFooter() {
            return this.outerCompleterFunction({
                productExport: 'product_export',
            });
        },
    },

    methods: {
        validateTemplate() {
            const notificationValidateSuccess = {
                message: this.$tc('sw-sales-channel.detail.productComparison.notificationMessageValidateSuccessful'),
            };

            this.isLoadingValidate = true;

            this.productExportService
                .validateProductExportTemplate(this.productExport)
                .then((data) => {
                    this.isLoadingValidate = false;

                    if (data.errors) {
                        this.previewContent = data.content;
                        this.previewErrors = data.errors;
                        return;
                    }

                    this.createNotificationSuccess(notificationValidateSuccess);
                    this.isValidateSuccessful = true;
                })
                .catch((exception) => {
                    this.createNotificationError({
                        message: exception.response.data.errors[0].detail,
                    });
                    warn(this._name, exception.message, exception.response);

                    this.isLoadingValidate = false;
                    this.isValidateSuccessful = false;
                });
        },

        preview() {
            this.isLoadingPreview = true;

            this.productExportService
                .previewProductExport(this.productExport)
                .then((data) => {
                    this.isLoadingPreview = false;
                    this.previewContent = data.content;

                    if (data.errors) {
                        this.previewErrors = data.errors;
                        return;
                    }

                    this.isPreviewSuccessful = true;
                })
                .catch((exception) => {
                    this.createNotificationError({
                        message: exception.response.data.errors[0].detail,
                    });
                    warn(this._name, exception.message, exception.response);

                    this.isLoadingPreview = false;
                });
        },

        outerCompleterFunction(mapping) {
            const entityMappingService = this.entityMappingService;

            return function completerFunction(prefix) {
                const entityMapping = entityMappingService.getEntityMapping(prefix, mapping);
                return Object.keys(entityMapping).map((val) => {
                    return { value: val };
                });
            };
        },

        onPreviewClose() {
            this.previewContent = null;
            this.previewErrors = null;
            this.isPreviewSuccessful = false;
        },

        async resetToDefault() {
            this.isLoadingReset = true;

            try {
                const providerName = this.productExport.provider || 'open-ai';
                const templates = await this.productExportService.getDefaultTemplate(providerName);

                this.productExport.headerTemplate = templates.headerTemplate;
                this.productExport.bodyTemplate = templates.bodyTemplate;
                this.productExport.footerTemplate = templates.footerTemplate;

                this.createNotificationInfo({
                    message: this.$tc('sw-sales-channel.detail.agenticAi.resetTemplateSuccess'),
                });
            } catch (exception) {
                this.createNotificationError({
                    message: this.$tc('sw-sales-channel.detail.agenticAi.errorLoadingTemplate'),
                });
            }

            this.isLoadingReset = false;
        },

        resetValid() {
            this.isValidateSuccessful = false;
        },
    },
};
