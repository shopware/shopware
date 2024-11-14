/**
 * @package services-settings
 */
import template from './sw-custom-field-type-number.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    data() {
        return {
            propertyNames: {
                label: this.$tc('sw-settings-custom-field.customField.detail.labelLabel'),
                placeholder: this.$tc('sw-settings-custom-field.customField.detail.labelPlaceholder'),
                helpText: this.$tc('sw-settings-custom-field.customField.detail.labelHelpText'),
            },
            numberTypes: [
                {
                    id: 'int',
                    name: this.$tc('sw-settings-custom-field.customField.detail.labelInt'),
                },
                {
                    id: 'float',
                    name: this.$tc('sw-settings-custom-field.customField.detail.labelFloat'),
                },
            ],
        };
    },

    watch: {
        'currentCustomField.config.numberType'(value) {
            this.currentCustomField.type = value;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.currentCustomField.config.numberType) {
                this.$set(this.currentCustomField.config, 'numberType', 'int');
            }
        },
    },
};
