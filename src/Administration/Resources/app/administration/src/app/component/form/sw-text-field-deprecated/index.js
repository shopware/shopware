import { inject } from 'vue';
import template from './sw-text-field-deprecated.html.twig';

const { Component, Mixin } = Shopware;

/**
 * @sw-package framework
 *
 * @private
 * @description Simple text field.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-text-field label="Name" placeholder="placeholder goes here..."></sw-text-field>
 * @deprecated tag:v6.8.0 - Will be removed, use mt-text-field instead
 */
Component.register('sw-text-field-deprecated', {
    template,

    inheritAttrs: false,

    inject: ['feature'],

    emits: [
        'update:value',
        'inheritance-restore',
        'inheritance-remove',
    ],

    mixins: [
        Mixin.getByName('sw-form-field'),
        Mixin.getByName('remove-api-error'),
        Mixin.getByName('validation'),
    ],

    props: {
        // eslint-disable-next-line vue/require-prop-types, vue/require-default-prop
        value: {
            required: false,
        },

        placeholder: {
            type: String,
            required: false,
            default: '',
        },

        copyable: {
            type: Boolean,
            required: false,
            default: false,
        },

        copyableTooltip: {
            type: Boolean,
            required: false,
            default: false,
        },

        idSuffix: {
            type: String,
            required: false,
            default() {
                return '';
            },
        },

        ariaLabel: {
            type: String,
            required: false,
            default() {
                return inject('ariaLabel', null)?.value;
            },
        },
    },

    data() {
        return {
            currentValue: this.value,
        };
    },

    computed: {
        hasPrefix() {
            return this.$slots.hasOwnProperty('prefix');
        },

        hasSuffix() {
            return this.$slots.hasOwnProperty('suffix');
        },

        filteredInputAttributes() {
            // Filter attributes and remove "size" attribute
            return Object.keys(this.$attrs).reduce((acc, key) => {
                const filteredValues = [
                    'size',
                    'class',
                ];

                if (!filteredValues.includes(key)) {
                    acc[key] = this.$attrs[key];
                }

                return acc;
            }, {});
        },
    },

    watch: {
        value(value) {
            this.currentValue = value;
        },
    },

    methods: {
        onChange(event) {
            this.$emit('update:value', event.target.value || '');
        },

        onInput(event) {
            this.$emit('update:value', event.target.value);
        },

        restoreInheritance() {
            this.$emit('update:value', null);
        },

        createInputId(identification) {
            if (!this.idSuffix || this.idSuffix.length <= 0) {
                return identification;
            }

            return `${identification}-${this.idSuffix}`;
        },
    },
});
