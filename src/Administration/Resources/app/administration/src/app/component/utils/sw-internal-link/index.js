import template from './sw-internal-link.html.twig';
import './sw-internal-link.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @description Link to another route inside the administration
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-internal-link
 *   :routerLink="{ name: "sw.product.index" }">
 *   Go to products
 * </sw-internal-link>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-internal-link', {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        routerLink: {
            type: Object,
            required: false,
            default: undefined,
        },

        target: {
            type: String,
            required: false,
            default: null,
        },

        icon: {
            type: String,
            required: false,
            default: 'regular-long-arrow-right',
        },

        inline: {
            type: Boolean,
            required: false,
            default: false,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        hideIcon: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        elementType() {
            return this.routerLink ? 'router-link' : 'a';
        },

        componentClasses() {
            return {
                'sw-internal-link--inline': this.inline,
            };
        },
    },
});
