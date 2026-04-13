/**
 * @sw-package framework
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
                    value: 'int',
                    label: this.$tc('sw-settings-custom-field.customField.detail.labelInt'),
                },
                {
                    value: 'float',
                    label: this.$tc('sw-settings-custom-field.customField.detail.labelFloat'),
                },
            ],
        };
    },

    computed: {
        isIntField() {
            return this.currentCustomField.config.numberType === 'int';
        },
    },

    watch: {
        'currentCustomField.config.numberType'(value) {
            this.currentCustomField.type = value;

            if (value === 'int') {
                if (this.currentCustomField.config.step !== null && this.currentCustomField.config.step !== undefined) {
                    const roundedStep = Math.round(this.currentCustomField.config.step);
                    this.currentCustomField.config.step = roundedStep >= 1 ? roundedStep : 1;
                }
                if (this.currentCustomField.config.min !== null && this.currentCustomField.config.min !== undefined) {
                    this.currentCustomField.config.min = Math.round(this.currentCustomField.config.min);
                }
                if (this.currentCustomField.config.max !== null && this.currentCustomField.config.max !== undefined) {
                    this.currentCustomField.config.max = Math.round(this.currentCustomField.config.max);
                }
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.currentCustomField.config.numberType) {
                this.currentCustomField.config.numberType = 'int';
            }
        },
    },
};
