import './sw-boolean-radio-group.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @description radio input field for boolean and named entries.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-boolean-radio-group
 *      label="Bool Radio group example"
 *      labelOptionTrue="Gross"
 *      labelOptionFalse="Net"
 *      :bordered="bordered">
 * </sw-boolean-radio-group>
 */
Component.register('sw-boolean-radio-group', {

    template:
`
<sw-radio-field
    class="sw-boolean-radio-group"
    v-bind="$attrs"
    :options="options"
    v-model:value="castedValue"
    name="sw-field--castedValue"
    :bordered="bordered">
</sw-radio-field>
`,

    inject: ['feature'],

    props: {
        value: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        labelOptionTrue: {
            type: String,
            required: true,
        },

        labelOptionFalse: {
            type: String,
            required: true,
        },

        bordered: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        options() {
            return [
                { value: 'true', name: this.labelOptionTrue },
                { value: 'false', name: this.labelOptionFalse },
            ];
        },

        castedValue: {
            get() {
                return this.value ? this.value.toString() : 'false';
            },

            set(val) {
                this.$emit('update:value', val === 'true');
            },
        },
    },
});
