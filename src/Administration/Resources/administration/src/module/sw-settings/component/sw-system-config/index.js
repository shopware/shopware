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
        }
    },

    data() {
        return {
            salesChannelId: null,
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
            this.readConfig()
                .then(this.readAll)
                .catch(({ response: { data } }) => {
                    if (data && data.errors) {
                        this.createErrorNotification(data.errors);
                    }
                });
        },
        readConfig() {
            this.isLoading = true;
            return this.systemConfigApiService
                .getConfig(this.domain, this.salesChannelId)
                .then(data => {
                    this.config = data;
                }).finally(() => {
                    this.isLoading = false;
                });
        },
        readAll() {
            this.isLoading = true;
            return this.systemConfigApiService.getValues(this.domain, this.salesChannelId)
                .then(values => {
                    this.actualConfigData = values;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },
        saveAll() {
            this.isLoading = true;
            return this.systemConfigApiService
                .saveValues(this.actualConfigData, this.salesChannelId)
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
        }
    }
});
