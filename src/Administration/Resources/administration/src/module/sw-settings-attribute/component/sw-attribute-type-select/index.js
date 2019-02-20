import { Component } from 'src/core/shopware';
import template from './sw-attribute-type-select.html.twig';
import './sw-attribute-type-select.scss';

Component.register('sw-attribute-type-select', {
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
                helpText: this.$tc('sw-settings-attribute.attribute.detail.labelHelpText')
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
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.currentAttribute.config.hasOwnProperty('options')) {
                this.$set(this.currentAttribute.config, 'options', []);
                this.addOption();
                this.addOption();
            }
        },
        addOption() {
            this.currentAttribute.config.options.push({ id: '', name: {} });
        },
        onClickAddOption() {
            this.addOption();
        },
        getLabel(locale) {
            const snippet = this.$tc('sw-settings-attribute.attribute.detail.labelLabel');
            const language = this.$tc(`locale.${locale}`);

            return `${snippet} (${language})`;
        },
        onDeleteOption(index) {
            this.currentAttribute.config.options.splice(index, 1);
        }
    }
});
