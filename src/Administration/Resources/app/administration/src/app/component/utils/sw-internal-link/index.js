import template from './sw-internal-link.html.twig';
import './sw-internal-link.scss';

const { Component } = Shopware;

/**
 * @public
 * @description Link to another route inside the administration
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-internal-link
 *   :routerLink="{ name: "sw.product.index" }">
 *   Go to products
 * </sw-internal-link>
 */
Component.register('sw-internal-link', {
    template,

    props: {
        routerLink: {
            type: Object,
            required: true,
        },

        target: {
            type: String,
            required: false,
            default: null,
        },

        icon: {
            type: String,
            required: false,
            default: 'default-arrow-simple-right',
        },

        inline: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        componentClasses() {
            return {
                'sw-internal-link--inline': this.inline,
            };
        },
    },
});
