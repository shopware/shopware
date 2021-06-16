import template from './sw-data-grid-skeleton.html.twig';
import './sw-data-grid-skeleton.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-data-grid-skeleton', {
    template,

    props: {
        currentColumns: {
            type: Array,
            required: true,
            default() {
                return [];
            },
        },
        itemAmount: {
            type: Number,
            required: false,
            default: 7,
        },
        showSelection: {
            type: Boolean,
            required: false,
            default: true,
        },
        showActions: {
            type: Boolean,
            required: false,
            default: true,
        },
        hasResizeColumns: {
            type: Boolean,
            required: true,
            default: false,
        },
    },
});
