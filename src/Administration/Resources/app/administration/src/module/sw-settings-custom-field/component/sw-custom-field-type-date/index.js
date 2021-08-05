import template from './sw-custom-field-type-date.html.twig';

const { Component } = Shopware;

Component.extend('sw-custom-field-type-date', 'sw-custom-field-type-base', {
    template,

    data() {
        return {
            propertyNames: {
                label: this.$tc('sw-settings-custom-field.customField.detail.labelLabel'),
                helpText: this.$tc('sw-settings-custom-field.customField.detail.labelHelpText'),
            },
            types: [
                { id: 'datetime', name: this.$tc('sw-settings-custom-field.customField.detail.labelDatetime') },
                { id: 'date', name: this.$tc('sw-settings-custom-field.customField.detail.labelDate') },
                { id: 'time', name: this.$tc('sw-settings-custom-field.customField.detail.labelTime') },
            ],
            timeForms: [
                { id: 'true', name: this.$tc('sw-settings-custom-field.customField.detail.labelYes') },
                { id: 'false', name: this.$tc('sw-settings-custom-field.customField.detail.labelNo') },
            ],
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.currentCustomField.config.hasOwnProperty('dateType')) {
                this.$set(this.currentCustomField.config, 'dateType', 'datetime');
            }

            if (!this.currentCustomField.config.hasOwnProperty('config')) {
                this.$set(this.currentCustomField.config, 'config', { time_24hr: true });
            }
        },
    },
});
