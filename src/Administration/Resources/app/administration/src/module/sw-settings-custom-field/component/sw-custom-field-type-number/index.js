import template from './sw-custom-field-type-number.html.twig';

const { Component } = Shopware;

Component.extend('sw-custom-field-type-number', 'sw-custom-field-type-base', {
    template,

    data() {
        return {
            propertyNames: {
                label: this.$tc('sw-settings-custom-field.customField.detail.labelLabel'),
                placeholder: this.$tc('sw-settings-custom-field.customField.detail.labelPlaceholder'),
                helpText: this.$tc('sw-settings-custom-field.customField.detail.labelHelpText'),
            },
            numberTypes: [
                { id: 'int', name: this.$tc('sw-settings-custom-field.customField.detail.labelInt') },
                { id: 'float', name: this.$tc('sw-settings-custom-field.customField.detail.labelFloat') },
            ],
        };
    },

    watch: {
        'currentCustomField.config.numberType'(value) {
            this.currentCustomField.type = value;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.currentCustomField.config.numberType) {
                this.$set(this.currentCustomField.config, 'numberType', 'int');
            }
        },
    },
});
