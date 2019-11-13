import template from './sw-system-config.html.twig';
import './sw-system-config.scss';

const { Component, Mixin } = Shopware;
const { object, types } = Shopware.Utils;

Component.register('sw-system-config', {
    name: 'sw-system-config',

    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('sw-inline-snippet')
    ],

    inject: ['systemConfigApiService'],

    props: {
        domain: {
            required: true,
            type: String
        },
        salesChannelId: {
            required: false,
            type: String,
            default: null
        },
        salesChannelSwitchable: {
            type: Boolean,
            required: false,
            default: false
        },
        // Shows the value of salesChannel=null as placeholder when the salesChannelSwitchable prop is true
        inherit: {
            type: Boolean,
            required: false,
            default: true
        }
    },

    data() {
        return {
            currentSalesChannelId: this.salesChannelId,
            isLoading: false,
            config: {},
            actualConfigData: {},
            salesChannelModel: null
        };
    },

    watch: {
        actualConfigData: {
            handler() {
                this.emitConfig();
            },
            deep: true
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            this.readConfig()
                .then(() => {
                    this.readAll().then(() => {
                        this.isLoading = false;
                    });
                })
                .catch(({ response: { data } }) => {
                    if (data && data.errors) {
                        this.createErrorNotification(data.errors);
                    }
                });
        },
        readConfig() {
            return this.systemConfigApiService
                .getConfig(this.domain)
                .then(data => {
                    this.config = data;
                });
        },
        readAll() {
            this.isLoading = true;

            // Return when data for this salesChannel was already loaded
            if (this.actualConfigData.hasOwnProperty(this.currentSalesChannelId)) {
                this.isLoading = false;
                return Promise.resolve();
            }

            return this.systemConfigApiService.getValues(this.domain, this.currentSalesChannelId)
                .then(values => {
                    this.$set(this.actualConfigData, this.currentSalesChannelId, values);
                })
                .finally(() => {
                    this.isLoading = false;
                });
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
                errors.length
            )}</div><ul>`;

            errors.forEach((error) => {
                message = `${message}<li>${error.detail}</li>`;
            });
            message += '</ul>';

            this.createNotificationError({
                title: this.$tc('sw-config-form-renderer.configLoadErrorTitle'),
                message: message,
                autoClose: false
            });
        },
        onSalesChannelChanged(salesChannelId) {
            this.currentSalesChannelId = salesChannelId;
            this.readAll();
        },
        getElementBind(element) {
            const bind = object.deepCopyObject(element);

            // Add inherited values
            if (this.currentSalesChannelId !== null
                    && this.inherit
                    && this.actualConfigData.hasOwnProperty('null')
                    && this.actualConfigData.null[bind.name] !== null
                    && !types.isUndefined(this.actualConfigData.null[bind.name])) {
                if (bind.type === 'single-select' || bind.config.componentName === 'sw-entity-single-select') {
                    // Add inherited placeholder option
                    bind.placeholder = this.$tc('sw-settings.system-config.inherited');
                } else if (bind.type === 'bool') {
                    // Add inheritedValue for checkbox fields to restore the inherited state
                    bind.config.inheritedValue = this.actualConfigData.null[bind.name];
                } else if (bind.type !== 'multi-select') {
                    // Add inherited placeholder
                    bind.placeholder = `${this.actualConfigData.null[bind.name]}`;
                }
            }

            // Add select properties
            if (['single-select', 'multi-select'].includes(bind.type)) {
                bind.config.labelProperty = 'name';
                bind.config.valueProperty = 'id';
            }

            return bind;
        },

        emitConfig() {
            this.$emit('config-changed', this.actualConfigData[this.currentSalesChannelId]);
        }
    }
});
