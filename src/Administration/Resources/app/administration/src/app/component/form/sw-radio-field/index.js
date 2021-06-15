import template from './sw-radio-field.html.twig';
import './sw-radio-field.scss';

const { Component, Mixin } = Shopware;

/**
 * @public
 * @description radio input field.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-radio-field
 *      label="Radio field example"
 *      bordered
 *      :options="[
 *          {'value': 'value1', 'name': 'Label #1'},
 *          {'value': 'value2', 'name': 'Label #2'},
 *          {'value': 'value3', 'name': 'Label #3'},
 *          {'value': 'value4', 'name': 'Label #4'},
 *          {'value': 'value5', 'name': 'Label #5'}
 * ]"></sw-radio-field>
 */
Component.register('sw-radio-field', {
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
        bordered: {
            type: Boolean,
            required: false,
            default: false,
        },

        block: {
            type: Boolean,
            required: false,
            default: false,
        },

        description: {
            type: String,
            required: false,
            default: null,
        },

        options: {
            type: Array,
            required: false,
            default: () => {
                return [];
            },
        },
        // FIXME: add type and default attribute to property
        // eslint-disable-next-line vue/require-prop-types, vue/require-default-prop
        value: {
            required: false,
        },
    },

    computed: {
        classes() {
            return [{
                'sw-field--radio-bordered': this.bordered,
                'sw-field--radio-block': this.block,
            }];
        },
        currentIndex() {
            const foundIndex = this.options.findIndex((item) => item.value === this.value);

            if (foundIndex < 0) {
                console.warn(`Given value "${this.value}" does not exists in given options`);
            }

            return foundIndex;
        },
    },

    methods: {
        onChange(event) {
            const selectedIndex = event.target.value;

            if (this.options[selectedIndex] === undefined) {
                console.warn(`Selected index "${this.value}" does not exists in given options`);
            }

            this.$emit('change', this.options[selectedIndex].value);
        },
    },
});
