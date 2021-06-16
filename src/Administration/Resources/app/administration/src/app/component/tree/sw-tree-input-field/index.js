import template from './sw-tree-input-field.html.twig';
import './sw-tree-input-field.scss';

const { Component } = Shopware;

/**
 * @private
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-tree-input-field>
 * </sw-tree-input-field>
 */
Component.register('sw-tree-input-field', {
    template,

    props: {
        // FIXME: add default value
        // eslint-disable-next-line vue/require-default-prop
        currentValue: {
            type: String,
            required: false,
        },

        disabled: {
            type: Boolean,
            default: false,
        },
    },

    computed: {
        classes() {
            return {
                'is--disabled': this.disabled,
            };
        },
    },

    methods: {
        createNewItem(itemName) {
            this.$emit('new-item-create', itemName);
        },
    },
});
