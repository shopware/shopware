import template from './sw-radio-panel.html.twig';
import './sw-radio-panel.scss';

const { Component } = Shopware;
const utils = Shopware.Utils;

/**
 * @public
 * @description Radio panel that can be used as an radio input with bigger content.
 * It is possible to define custom content with a slot.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-radio-panel value="selectionValueIfSelected"
 * title="Example title"
 * description="Example description"
 * icon="default-badge-warning"
 * ></sw-radio-panel>
 */
Component.register('sw-radio-panel', {
    template,

    model: {
        prop: 'modelValue', // use the variable 'modelValue' instead of 'value' because both are relevant!
        event: 'input',
    },

    props: {
        // FIXME: add require flag, add default value
        // eslint-disable-next-line vue/require-default-prop
        value: {
            type: String,
        },
        // FIXME: add require flag, add default value
        // eslint-disable-next-line vue/require-default-prop
        modelValue: {
            type: String,
        },
        title: {
            type: String,
            default: '',
        },
        description: {
            type: String,
            default: '',
        },
        icon: {
            type: String,
            default: '',
        },
        id: {
            type: String,
            default() {
                return `sw-radio-panel--${utils.createId()}`;
            },
        },
        name: {
            type: String,
            default: null,
        },
        required: {
            type: Boolean,
            default: false,
        },
        disabled: {
            type: Boolean,
            default: false,
        },
        truncate: {
            type: Boolean,
            default: false,
        },
    },

    computed: {
        checked() {
            return this.modelValue === this.value;
        },
    },

    methods: {
        toggle() {
            this.$emit('input', this.value);
        },
    },
});
