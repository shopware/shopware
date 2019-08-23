import template from './sw-custom-field-type-base.html.twig';

const { Component } = Shopware;

Component.register('sw-custom-field-type-base', {
    template,

    props: {
        currentCustomField: {
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
                label: this.$tc('sw-settings-custom-field.customField.detail.labelLabel')
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
