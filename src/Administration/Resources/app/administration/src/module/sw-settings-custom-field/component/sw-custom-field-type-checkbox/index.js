const { Component } = Shopware;

Component.extend('sw-custom-field-type-checkbox', 'sw-custom-field-type-base', {
    data() {
        return {
            propertyNames: {
                label: this.$tc('sw-settings-custom-field.customField.detail.labelLabel'),
                helpText: this.$tc('sw-settings-custom-field.customField.detail.labelHelpText'),
            },
        };
    },
});
