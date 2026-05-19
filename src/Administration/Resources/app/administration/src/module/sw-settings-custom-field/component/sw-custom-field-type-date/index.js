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
                label: this.$t('sw-settings-custom-field.customField.detail.labelLabel'),
                helpText: this.$t('sw-settings-custom-field.customField.detail.labelHelpText'),
            },
            types: [
                {
                    value: 'datetime',
                    label: this.$t('sw-settings-custom-field.customField.detail.labelDatetime'),
                },
                {
                    value: 'date',
                    label: this.$t('sw-settings-custom-field.customField.detail.labelDate'),
                },
                {
                    value: 'time',
                    label: this.$t('sw-settings-custom-field.customField.detail.labelTime'),
                },
            ],
            timeForms: [
                {
                    value: 'true',
                    label: this.$t('global.default.yes'),
                },
                {
                    value: 'false',
                    label: this.$t('global.default.no'),
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
