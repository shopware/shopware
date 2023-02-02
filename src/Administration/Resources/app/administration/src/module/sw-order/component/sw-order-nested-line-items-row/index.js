import template from './sw-order-nested-line-items-row.html.twig';
import './sw-order-nested-line-items-row.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-order-nested-line-items-row', {
    template,

    props: {
        lineItem: {
            type: Object,
            required: true,
        },

        currency: {
            type: Object,
            required: true,
        },

        renderParent: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default() {
                return false;
            },
        },
    },

    methods: {
        getNestingClasses(nestingLevel) {
            return [
                `nesting-level-${nestingLevel}`,
            ];
        },
    },
});
