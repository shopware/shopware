/**
 * @sw-package framework
 */
import template from './sw-custom-field-type-date.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    data() {
        return {
            propertyNames: {
                label: this.$tc('sw-settings-custom-field.customField.detail.labelLabel'),
                helpText: this.$tc('sw-settings-custom-field.customField.detail.labelHelpText'),
            },
            types: [
                {
                    option: 'datetime',
                    label: this.$tc('sw-settings-custom-field.customField.detail.labelDatetime'),
                },
                {
                    option: 'date',
                    label: this.$tc('sw-settings-custom-field.customField.detail.labelDate'),
                },
                {
                    option: 'time',
                    label: this.$tc('sw-settings-custom-field.customField.detail.labelTime'),
                },
            ],
            timeForms: [
                {
                    option: 'true',
                    label: this.$tc('global.default.yes'),
                },
                {
                    option: 'false',
                    label: this.$tc('global.default.no'),
                },
            ],
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.currentCustomField.config.hasOwnProperty('dateType')) {
                this.currentCustomField.config.dateType = 'datetime';
            }

            if (!this.currentCustomField.config.hasOwnProperty('config')) {
                this.currentCustomField.config.config = {
                    time_24hr: true,
                };
            }
        },
    },
};
