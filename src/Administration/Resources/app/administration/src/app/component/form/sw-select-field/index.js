import template from './sw-select-field.html.twig';
import './sw-select-field.scss';

const { Component, Mixin } = Shopware;

/**
 * @public
 * @description select input field.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-select-field placeholder="placeholder goes here..." label="label">
 *     <option value="value1">Label #1</option>
 *     <option value="value2">Label #2</option>
 *     <option value="value3">Label #3</option>
 *     <option value="value4">Label #4</option>
 *     <option value="value5">Label #5</option>
 * </sw-select-field>
 */
Component.register('sw-select-field', {
    template,

    inheritAttrs: false,

    mixins: [
        Mixin.getByName('sw-form-field'),
        Mixin.getByName('remove-api-error'),
    ],

    model: {
        prop: 'value',
        event: 'change',
    },

    props: {
        value: {
            type: String,
            required: false,
            default: null,
        },

        placeholder: {
            type: String,
            required: false,
            default: null,
        },

        options: {
            type: Array,
            required: false,
            default: null,
        },

        aside: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            currentValue: this.value,
        };
    },

    computed: {
        locale() {
            return this.$root.$i18n.locale;
        },

        fallbackLocale() {
            return this.$root.$i18n.fallbackLocale;
        },

        swFieldSelectClasses() {
            return {
                'sw-field--select-aside': this.aside && this.$attrs.label,
            };
        },

        hasOptions() {
            return this.options && this.options.length;
        },
    },

    watch: {
        value() { this.currentValue = this.value; },
    },

    methods: {
        getOptionName(name) {
            if (name) {
                if (name[this.locale]) {
                    return name[this.locale];
                }

                if (name[this.fallbackLocale]) {
                    return name[this.fallbackLocale];
                }

                return name;
            }

            return '';
        },

        onChange(event) {
            this.currentValue = event.target.value;
            if (event.target.value === '') {
                this.currentValue = null;
            }

            this.$emit('change', this.currentValue);
        },
    },
});
