import template from './sw-external-link.html.twig';
import './sw-external-link.scss';

const { Component } = Shopware;

/**
 * @public
 * @description Link to another website outside the admin, that opens in a new browser tab
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-external-link
 *   href="https://google.com">
 *   Ask google
 * </sw-external-link>
 */
Component.register('sw-external-link', {
    template,

    inheritAttrs: false,

    props: {
        small: {
            type: Boolean,
            required: false,
            default: false,
        },

        icon: {
            type: String,
            required: false,
            default: 'small-arrow-small-external',
        },
    },

    computed: {
        classes() {
            return {
                'sw-external-link--small': this.small,
            };
        },

        iconSize() {
            if (this.small) {
                return '8px';
            }

            return '10px';
        },
    },

    methods: {
        onClick(event) {
            this.$emit('click', event);
        },
    },
});
