import template from './sw-system-config.html.twig';
import './sw-system-config.scss';

const { Component, Mixin } = Shopware;
const { object, string: { kebabCase } } = Shopware.Utils;

Component.register('sw-system-config', {

    template,

    inject: ['systemConfigApiService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('sw-inline-snippet'),
    ],

    props: {
        domain: {
            required: true,
            type: String,
        },
        salesChannelId: {
            required: false,
            type: String,
            default: null,
        },
        salesChannelSwitchable: {
            type: Boolean,
            required: false,
            default: false,
        },
        // Shows the value of salesChannel=null as placeholder when the salesChannelSwitchable prop is true
        inherit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            currentSalesChannelId: this.salesChannelId,
            isLoading: false,
            config: {},
            actualConfigData: {},
            salesChannelModel: null,
        };
    },

    computed: {
        isNotDefaultSalesChannel() {
            return this.currentSalesChannelId !== null;
        },

        typesWithMapInheritanceSupport() {
            return [
                'text',
                'textarea',
                'url',
                'password',
                'int',
                'float',
                'bool',
                'checkbox',
                'colorpicker',
            ];
        },
    },

    watch: {
        actualConfigData: {
            handler() {
                this.emitConfig();
            },
            deep: true,
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;
            try {
                await this.readConfig();
                await this.readAll();
            } catch (error) {
                if (error?.response?.data?.errors) {
                    this.createErrorNotification(error.response.data.errors);
                }
            } finally {
                this.isLoading = false;
            }
        },
        async readConfig() {
            this.config = await this.systemConfigApiService.getConfig(this.domain);
        },
        readAll() {
            this.isLoading = true;
            // Return when data for this salesChannel was already loaded
            if (this.actualConfigData.hasOwnProperty(this.currentSalesChannelId)) {
                this.isLoading = false;
                return Promise.resolve();
            }

            return this.loadCurrentSalesChannelConfig();
        },
        async loadCurrentSalesChannelConfig() {
            this.isLoading = true;

            try {
                const values = await this.systemConfigApiService.getValues(this.domain, this.currentSalesChannelId);

                this.$set(this.actualConfigData, this.currentSalesChannelId, values);
            } finally {
                this.isLoading = false;
            }
        },
        saveAll() {
            this.isLoading = true;
            return this.systemConfigApiService
                .batchSave(this.actualConfigData)
                .finally(() => {
                    this.isLoading = false;
                });
        },
        createErrorNotification(errors) {
            let message = `<div>${this.$tc(
                'sw-config-form-renderer.configLoadErrorMessage',
                errors.length,
            )}</div><ul>`;

            errors.forEach((error) => {
                message = `${message}<li>${error.detail}</li>`;
            });
            message += '</ul>';

            this.createNotificationError({
                message: message,
                autoClose: false,
            });
        },
        onSalesChannelChanged(salesChannelId) {
            this.currentSalesChannelId = salesChannelId;
            this.readAll();
        },

        hasMapInheritanceSupport(element) {
            const componentName = element.config ? element.config.componentName : undefined;

            if (componentName === 'sw-switch-field' || componentName === 'sw-snippet-field') {
                return true;
            }

            return this.typesWithMapInheritanceSupport.includes(element.type);
        },

        getElementBind(element, mapInheritance) {
            const bind = object.deepCopyObject(element);

            if (!this.hasMapInheritanceSupport(element)) {
                delete bind.config.label;
                delete bind.config.helpText;
            } else {
                bind.mapInheritance = mapInheritance;
            }

            // Add select properties
            if (['single-select', 'multi-select'].includes(bind.type)) {
                bind.config.labelProperty = 'name';
                bind.config.valueProperty = 'id';
            }

            if (element.type === 'text-editor') {
                bind.config.componentName = 'sw-text-editor';
            }

            return bind;
        },

        getInheritWrapperBind(element) {
            if (this.hasMapInheritanceSupport(element)) {
                return {};
            }

            return {
                label: this.getInlineSnippet(element.config.label),
                helpText: this.getInlineSnippet(element.config.helpText),
            };
        },

        getInheritedValue(element) {
            const value = this.actualConfigData.null[element.name];

            if (value) {
                return value;
            }

            if (element.config?.componentName) {
                const componentName = element.config.componentName;

                if (componentName === 'sw-switch-field') {
                    return false;
                }
            }

            switch (element.type) {
                case 'date':
                case 'datetime':
                case 'single-select':
                case 'colorpicker':
                case 'password':
                case 'url':
                case 'text':
                case 'textarea':
                case 'text-editor': {
                    return '';
                }

                case 'multi-select': {
                    return [];
                }

                case 'checkbox':
                case 'bool': {
                    return false;
                }

                case 'float':
                case 'int': {
                    return 0;
                }

                default: {
                    return null;
                }
            }
        },

        emitConfig() {
            this.$emit('config-changed', this.actualConfigData[this.currentSalesChannelId]);
        },

        kebabCase(value) {
            return kebabCase(value);
        },
    },
});
