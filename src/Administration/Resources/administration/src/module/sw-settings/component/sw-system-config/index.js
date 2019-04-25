import { Mixin, Component } from 'src/core/shopware';
import template from './sw-system-config.html.twig';
import './sw-system-config.scss';

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
            default: false
        }
    },

    data() {
        return {
            currentSalesChannelId: this.salesChannelId,
            isLoading: false,
            config: {},
            actualConfigData: {}
        };
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
            // Return when data for this salesChannel was already loaded
            if (this.actualConfigData.hasOwnProperty(this.currentSalesChannelId)) {
                return Promise.resolve();
            }

            this.isLoading = true;


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
            // Replace the placeholder with inherited if possible/needed
            if (this.currentSalesChannelId !== null
                    && this.inherit
                    && this.actualConfigData.hasOwnProperty('null')) {
                element.placeholder = this.actualConfigData.null[element.name];
            }

            return element;
        }
    }
});
