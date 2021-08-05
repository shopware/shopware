const { Component } = Shopware;

Component.extend('sw-custom-field-type-text-editor', 'sw-custom-field-type-base', {
    data() {
        return {
            propertyNames: {
                label: this.$tc('sw-settings-custom-field.customField.detail.labelLabel'),
                placeholder: this.$tc('sw-settings-custom-field.customField.detail.labelPlaceholder'),
            },
        };
    },
});
