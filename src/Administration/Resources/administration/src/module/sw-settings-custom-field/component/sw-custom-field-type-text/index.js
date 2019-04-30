import { Component } from 'src/core/shopware';

Component.extend('sw-custom-field-type-text', 'sw-custom-field-type-base', {
    data() {
        return {
            propertyNames: {
                label: this.$tc('sw-settings-custom-field.customField.detail.labelLabel'),
                placeholder: this.$tc('sw-settings-custom-field.customField.detail.labelPlaceholder'),
                helpText: this.$tc('sw-settings-custom-field.customField.detail.labelHelpText'),
                tooltipText: this.$tc('sw-settings-custom-field.customField.detail.labelTooltipText')
            }
        };
    }
});
