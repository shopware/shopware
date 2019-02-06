import { Mixin } from 'src/core/shopware';
import template from './sw-config-form-renderer.html.twig';

export default {
    name: 'sw-config-form-renderer',

    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    inject: ['configFormRendererService'],

    props: {
        value: {
            type: String,
            required: false,
            default: ''
        }
    },

    data() {
        return {
            config: {},
            locale: {},
            fallbackLocale: {},
            defaultHelpText: ''
        };
    },

    created() {
        this.createdComponent();
    },


    methods: {
        createdComponent() {
            this.locale = this.$root.$i18n.locale.replace('-', '_');
            this.fallbackLocale = this.$root.$i18n.fallbackLocale.replace('-', '_');
            // TODO remove sample data while building the plugin manager
            this.configFormRendererService.getConfig(
                { namespace: 'SwagExample', sales_channel_id: '20080911FFFF4FFFAFFFFFFF19830531' }
            ).then((data) => {
                this.config = data;
            }).catch((errorResponse) => {
                if (errorResponse.response.data && errorResponse.response.data.errors) {
                    let message = `${this.$tc('sw-config-form-renderer.configLoadErrorMessage',
                        errorResponse.response.data.errors.length)}<br><br><ul>`;
                    errorResponse.response.data.errors.forEach((error) => {
                        message = `${message}<li>${error.detail}</li>`;
                    });
                    message += '</li>';
                    this.createNotificationError({
                        title: this.$tc('sw-config-form-renderer.configLoadErrorTitle'),
                        message: message,
                        autoClose: false
                    });
                }
                this.isLoading = false;
            });
        },

        getLabel(field) {
            if (!field.label) {
                return field.name;
            }

            if (field.label[this.locale]) {
                return field.label[this.locale];
            }

            if (field.label[this.fallbackLocale]) {
                return field.label[this.fallbackLocale];
            }

            return field.name;
        },

        getPlaceholder(field) {
            if (!field.placeholder) {
                return this.$tc('sw-config-form-renderer.placeholder', 0, { name: this.getLabel(field) });
            }

            if (field.placeholder[this.locale]) {
                return field.placeholder[this.locale];
            }

            if (field.placeholder[this.fallbackLocale]) {
                return field.placeholder[this.fallbackLocale];
            }

            return this.$tc('sw-config-form-renderer.placeholder', 0, { name: this.getLabel(field) });
        },

        getHelpText(field) {
            if (!field.helpText) {
                return this.defaultHelpText;
            }

            if (field.helpText[this.locale]) {
                return field.helpText[this.locale];
            }

            if (field.helpText[this.fallbackLocale]) {
                return field.helpText[this.fallbackLocale];
            }

            return this.defaultHelpText;
        },

        getOptions(field) {
            if (!field.options) {
                return [];
            }

            const options = [];
            let label;

            for (let i = 0; i < field.options.length; i += 1) {
                label = this.getOptionLabel(field.options[i]);
                options[i] = { value: field.options[i].value, name: label };
            }

            return options;
        },

        getOptionLabel(option) {
            if (option.label[this.locale]) {
                return option.label[this.locale];
            }

            if (option.label[this.fallbackLocale]) {
                return option.label[this.fallbackLocale];
            }

            return this.$tc('sw-config-form-renderer.option', 0, { locale: this.locale });
        }
    }
};
