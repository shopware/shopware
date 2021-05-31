import template from './sw-bulk-edit-change-type-field-renderer.html.twig';
import './sw-bulk-edit-change-type-field-renderer.scss';

const { Component } = Shopware;

Component.register('sw-bulk-edit-change-type-field-renderer', {
    template,

    props: {
        bulkEditData: {
            type: Object,
            required: true,
        },
        formFields: {
            type: Array,
            required: true,
        },
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
            return this.getConfigValue(formField, 'allowOverwrite') === true ||
                this.getConfigValue(formField, 'allowClear') === true ||
                this.getConfigValue(formField, 'allowAdd') === true ||
                this.getConfigValue(formField, 'allowRemove') === true;
        },
    },
});
