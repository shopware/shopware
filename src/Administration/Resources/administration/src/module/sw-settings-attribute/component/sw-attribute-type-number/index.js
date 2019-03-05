import { Component } from 'src/core/shopware';
import template from './sw-attribute-type-number.html.twig';

Component.extend('sw-attribute-type-number', 'sw-attribute-type-base', {
    template,

    data() {
        return {
            propertyNames: {
                label: this.$tc('sw-settings-attribute.attribute.detail.labelLabel'),
                placeholder: this.$tc('sw-settings-attribute.attribute.detail.labelPlaceholder'),
                helpText: this.$tc('sw-settings-attribute.attribute.detail.labelHelpText'),
                tooltipText: this.$tc('sw-settings-attribute.attribute.detail.labelTooltipText')
            },
            numberTypes: [
                { id: 'int', name: this.$tc('sw-settings-attribute.attribute.detail.labelInt') },
                { id: 'float', name: this.$tc('sw-settings-attribute.attribute.detail.labelFloat') }
            ]
        };
    },

    watch: {
        'currentAttribute.config.numberType'(value) {
            this.currentAttribute.type = value;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.currentAttribute.config.numberType) {
                this.$set(this.currentAttribute.config, 'numberType', 'int');
            }
        }
    }
});
