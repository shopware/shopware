/**
 * @package services-settings
 */
import template from './sw-bulk-edit-change-type-field-renderer.html.twig';
import './sw-bulk-edit-change-type-field-renderer.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['feature'],

    emits: [
        'change-value',
        'inheritance-restore',
        'inheritance-remove',
    ],

    props: {
        bulkEditData: {
            type: Object,
            required: true,
        },
        formFields: {
            type: Array,
            required: true,
        },
        entity: {
            type: Object,
            required: true,
        },
        disabled: {
            type: Boolean,
            default: false,
        },
    },

    data() {
        return {
            isDisplayingValue: true,
        };
    },

    methods: {
        hasFormFieldConfig(formField) {
            return !!formField.config;
        },

        getConfigValue(formField, key) {
            if (!this.hasFormFieldConfig(formField)) {
                return null;
            }

            if (!formField.config[key]) {
                return null;
            }

            return formField.config[key];
        },

        showSelectBoxType(formField) {
            return (
                this.getConfigValue(formField, 'allowOverwrite') === true ||
                this.getConfigValue(formField, 'allowClear') === true ||
                this.getConfigValue(formField, 'allowAdd') === true ||
                this.getConfigValue(formField, 'allowRemove') === true
            );
        },

        onChangeValue(value, fieldName, valueChange = true) {
            if (valueChange) {
                this.entity[fieldName] = value;
            }

            if (!this.bulkEditData[fieldName].isInherited) {
                this.bulkEditData[fieldName].value = value;
            }
            this.$emit('change-value', fieldName, value);
        },

        onChangeToggle(value, fieldName) {
            this.onChangeValue(value, fieldName, false);
        },

        onInheritanceRestore(item) {
            this.$emit('inheritance-restore', item);
        },

        onInheritanceRemove(item) {
            this.$emit('inheritance-remove', item);
        },
    },
};
