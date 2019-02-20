import { Component } from 'src/core/shopware';
import template from './sw-attribute-type-colorpicker.html.twig';

Component.register('sw-attribute-type-colorpicker', {
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
                label: this.$tc('sw-settings-attribute.attribute.detail.labelLabel')
            }
        };
    },

    computed: {
        locales() {
            if (this.set.config.hasOwnProperty('translated') && this.set.config.translated === true) {
                return Object.keys(this.$root.$i18n.messages);
            }

            return [this.$root.$i18n.fallbackLocale];
        }
    }
});
