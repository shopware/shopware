import { Component } from 'src/core/shopware';
import template from './sw-attribute-type-date.html.twig';

Component.extend('sw-attribute-type-date', 'sw-attribute-type-base', {
    template,

    data() {
        return {
            propertyNames: {
                label: this.$tc('sw-settings-attribute.attribute.detail.labelLabel'),
                helpText: this.$tc('sw-settings-attribute.attribute.detail.labelHelpText')
            },
            types: [
                { id: 'datetime', name: this.$tc('sw-settings-attribute.attribute.detail.labelDatetime') },
                { id: 'date', name: this.$tc('sw-settings-attribute.attribute.detail.labelDate') },
                { id: 'time', name: this.$tc('sw-settings-attribute.attribute.detail.labelTime') }
            ],
            timeForms: [
                { id: 'true', name: this.$tc('sw-settings-attribute.attribute.detail.labelYes') },
                { id: 'false', name: this.$tc('sw-settings-attribute.attribute.detail.labelNo') }
            ]
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.currentAttribute.config.hasOwnProperty('dateType')) {
                this.$set(this.currentAttribute.config, 'dateType', 'datetime');
            }

            if (!this.currentAttribute.config.hasOwnProperty('config')) {
                this.$set(this.currentAttribute.config, 'config', { time_24hr: true });
            }
        }
    }
});
