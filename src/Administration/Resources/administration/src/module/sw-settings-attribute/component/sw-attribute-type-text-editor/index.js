import { Component } from 'src/core/shopware';

Component.extend('sw-attribute-type-text-editor', 'sw-attribute-type-base', {
    data() {
        return {
            propertyNames: {
                label: this.$tc('sw-settings-attribute.attribute.detail.labelLabel'),
                placeholder: this.$tc('sw-settings-attribute.attribute.detail.labelPlaceholder')
            }
        };
    }
});
