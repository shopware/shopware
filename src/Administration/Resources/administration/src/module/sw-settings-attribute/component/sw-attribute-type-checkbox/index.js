import { Component } from 'src/core/shopware';

Component.extend('sw-attribute-type-checkbox', 'sw-attribute-type-base', {
    data() {
        return {
            propertyNames: {
                label: this.$tc('sw-settings-attribute.attribute.detail.labelLabel'),
                helpText: this.$tc('sw-settings-attribute.attribute.detail.labelHelpText')
            }
        };
    }
});
