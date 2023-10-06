/*
 * @package business-ops
 */

import template from './sw-product-stream-field-select.html.twig';
import './sw-product-stream-field-select.scss';

/**
 * @private
 */
export default {
    template,

    inject: [
        'conditionDataProviderService',
        'productCustomFields',
    ],

    props: {
        definition: {
            type: Object,
            required: true,
        },

        field: {
            type: String,
            required: false,
            default: null,
        },

        index: {
            type: Number,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        hasError: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        options() {
            const entityFields = Object.keys(this.definition.properties).map((property) => {
                if (!this.conditionDataProviderService.isPropertyInAllowList(this.definition.entity, property)) {
                    return null;
                }

                if (property === 'id') {
                    return {
                        label: this.getPropertyTranslation(this.definition.entity),
                        value: property,
                    };
                }

                return {
                    label: this.getPropertyTranslation(property),
                    value: property,
                };
            }).filter((option) => option !== null);

            if (this.definition.entity === 'product') {
                Object.values(this.conditionDataProviderService.allowedJsonAccessors).forEach((field) => {
                    entityFields.push({
                        label: this.getPropertyTranslation(field.trans),
                        value: field.value,
                    });
                });

                Object.keys(this.productCustomFields).forEach((customField) => {
                    entityFields.push(this.productCustomFields[customField]);
                });
            }

            return entityFields;
        },

        arrowPrimaryColor() {
            if (this.hasError) {
                return '#de294c';
            }

            return '#758ca3';
        },
    },

    watch: {
        'definition.entity': {
            immediate: true,
            handler(value) {
                // emit change when there is only one selectable option
                if (!!value && this.options.length === 1 && !this.field) {
                    this.changeField(this.options[0].value);
                }
            },
        },

        field: {
            handler(value) {
                // emit change when there is only one selectable option
                if (!!this.definition.entity && this.options.length === 1 && !value) {
                    this.changeField(this.options[0].value);
                }
            },
        },
    },

    methods: {
        changeField(value) {
            this.$emit('field-changed', { field: value, index: this.index });
        },

        getPropertyTranslation(property) {
            const translationKey = `sw-product-stream.filter.values.${property}`;
            const translated = this.$tc(translationKey);

            return translated === translationKey ? property : translated;
        },
    },
};
