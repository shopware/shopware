const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @description select input field. Values will be transformed to numbers.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-select-number-field placeholder="placeholder goes here..." label="label">
 *     <option value="1">Label #1</option>
 *     <option value="2">Label #2</option>
 *     <option value="3">Label #3</option>
 *     <option value="4">Label #4</option>
 *     <option value="5">Label #5</option>
 * </sw-select-number-field>
 */
Component.extend('sw-select-number-field', 'sw-select-field', {

    inheritAttrs: false,

    inject: ['feature'],

    model: {
        prop: 'value',
        event: 'change',
    },

    props: {
        value: {
            type: Number,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            currentValue: Number(this.value),
        };
    },

    watch: {
        value() { this.currentValue = Number(this.value); },
    },

    methods: {
        onChange(event) {
            this.currentValue = Number(event.target.value);

            if (Number.isNaN(this.currentValue)) {
                this.currentValue = null;
            }

            this.$emit('update:value', this.currentValue);
        },
    },
});
