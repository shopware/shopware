import { Component } from 'src/core/shopware';

Component.extend('sw-attribute-type-text', 'sw-attribute-type-base', {
    data() {
        return {
            propertyNames: {
                label: this.$tc('sw-settings-attribute.attribute.detail.labelLabel'),
                placeholder: this.$tc('sw-settings-attribute.attribute.detail.labelPlaceholder'),
                helpText: this.$tc('sw-settings-attribute.attribute.detail.labelHelpText'),
                tooltipText: this.$tc('sw-settings-attribute.attribute.detail.labelTooltipText')
            }
        };
    }
});
