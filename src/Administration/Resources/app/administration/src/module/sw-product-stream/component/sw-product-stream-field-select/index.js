import template from './sw-product-stream-field-select.html.twig';
import './sw-product-stream-field-select.scss';

const { Component } = Shopware;

Component.register('sw-product-stream-field-select', {
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
                Object.keys(this.productCustomFields).forEach((customField) => {
                    entityFields.push(this.productCustomFields[customField]);
                });
            }

            return entityFields;
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
});
