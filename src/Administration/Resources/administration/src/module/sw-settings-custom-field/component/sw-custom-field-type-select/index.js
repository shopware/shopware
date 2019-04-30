import { Component } from 'src/core/shopware';
import template from './sw-custom-field-type-select.html.twig';
import './sw-custom-field-type-select.scss';

Component.extend('sw-custom-field-type-select', 'sw-custom-field-type-base', {
    template,

    data() {
        return {
            propertyNames: {
                label: this.$tc('sw-settings-custom-field.customField.detail.labelLabel'),
                placeholder: this.$tc('sw-settings-custom-field.customField.detail.labelPlaceholder'),
                helpText: this.$tc('sw-settings-custom-field.customField.detail.labelHelpText')
            }
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.currentCustomField.config.hasOwnProperty('options')) {
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
            const snippet = this.$tc('sw-settings-custom-field.customField.detail.labelLabel');
            const language = this.$tc(`locale.${locale}`);

            return `${snippet} (${language})`;
        },
        onDeleteOption(index) {
            this.currentCustomField.config.options.splice(index, 1);
        }
    }
});
