import { Component } from 'src/core/shopware';
import template from './sw-attribute-type-number.html.twig';

Component.register('sw-attribute-type-number', {
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

    computed: {
        locales() {
            if (this.set.config.hasOwnProperty('translated') && this.set.config.translated === true) {
                return Object.keys(this.$root.$i18n.messages);
            }

            return [this.$root.$i18n.fallbackLocale];
        }
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
