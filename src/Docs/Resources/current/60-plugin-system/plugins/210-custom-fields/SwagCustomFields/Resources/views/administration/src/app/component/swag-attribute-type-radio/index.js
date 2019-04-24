import { Component } from 'src/core/shopware';
import template from './swag-custom-field-type-radio.html.twig';
import './swag-custom-field-type-radio.scss';

Component.register('swag-custom-field-type-radio', {
    template,

    props: {
        // Automatically passed as prop when the component is created
        currentCustomField: {
            type: Object,
            required: true
        },
        // Automatically passed as prop when the component is created
        set: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            // Text properties that should be translatable, if the set is translated
            propertyNames: {
                label: this.$tc('sw-settings-custom-field.customField.detail.labelLabel')
            }
        };
    },

    computed: {
        // Check if the set is translatable, else only provide a input for the fallback locale
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
            // If no options set, initialise the component with two options
            if (!this.currentCustomField.config.hasOwnProperty('options')) {
                // We need the $set function for nested objects to enable vue reactivity for the property
                this.$set(this.currentCustomField.config, 'options', []);
                this.addOption();
                this.addOption();
            }
        },
        addOption() {
            this.currentCustomField.config.options.push({ id: '', name: {} });
        },
        onClickAddOption() {
            this.addOption();
        },
        getLabel(locale) {
            const snippet = this.$tc('swag-custom-field-type-radio.labelLabel');
            const language = this.$tc(`locale.${locale}`);

            return `${snippet} (${language})`;
        },
        onDeleteOption(index) {
            this.currentCustomField.config.options.splice(index, 1);
        }
    }
});
