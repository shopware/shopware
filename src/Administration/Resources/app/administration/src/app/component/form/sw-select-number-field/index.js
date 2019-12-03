const { Component } = Shopware;

/**
 * @public
 * @description select input field. Values will be transformed to numbers.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-number-select-field placeholder="placeholder goes here..." label="label">
 *     <option value="1">Label #1</option>
 *     <option value="2">Label #2</option>
 *     <option value="3">Label #3</option>
 *     <option value="4">Label #4</option>
 *     <option value="5">Label #5</option>
 * </sw-number-select-field>
 */
Component.extend('sw-select-number-field', 'sw-select-field', {

    inheritAttrs: false,

    props: {
        value: {
            type: Number,
            required: false,
            default: null
        }
    },

    computed: {
        get() {
            return Number(this.value);
        },

        set(newValue) {
            const numberValue = Number(newValue);

            if (Number.isNaN(numberValue)) {
                this.$emit('change', null);
                return;
            }

            this.$emit('change', numberValue);
        }
    },

    methods: {
        onChange(event) {
            this.currentValue = event.target.value;
        }
    }
});
