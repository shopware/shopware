import template from './sw-data-grid-inline-edit.html.twig';
import './sw-data-grid-inline-edit.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-data-grid-inline-edit', {
    template,

    props: {
        column: {
            type: Object,
            required: true,
            default() {
                return {};
            },
        },
        // FIXME: add property type
        // eslint-disable-next-line vue/require-prop-types
        value: {
            required: true,
        },
        compact: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            currentValue: null,
        };
    },

    computed: {
        classes() {
            return {
                'is--compact': this.compact,
            };
        },

        inputFieldSize() {
            return this.compact ? 'small' : 'default';
        },
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.beforeDestroyComponent();
    },

    methods: {
        createdComponent() {
            this.currentValue = this.value;

            this.$parent.$on('inline-edit-assign', this.emitInput);
        },

        beforeDestroyComponent() {
            this.$parent.$off('inline-edit-assign', this.emitInput);
        },

        emitInput() {
            this.$emit('input', this.currentValue);
        },
    },
});
