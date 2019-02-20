import { Component } from 'src/core/shopware';
import template from './sw-attribute-type-date.html.twig';

Component.register('sw-attribute-type-date', {
    template,

    props: {
        currentAttribute: {
            type: Object,
            required: true
        },
        set: {
            type: Object,
            required: true
        }
    },

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

    computed: {
        locales() {
            if (this.set.config.hasOwnProperty('translated') && this.set.config.translated === true) {
                return Object.keys(this.$root.$i18n.messages);
            }

            return [this.$root.$i18n.fallbackLocale];
        }
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
