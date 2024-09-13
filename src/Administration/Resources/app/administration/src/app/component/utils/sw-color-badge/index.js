import template from './sw-color-badge.html.twig';
import './sw-color-badge.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @description
 * Renders a colored badge for example as indicator if an item is available.
 * @status ready
 * @example-type static
 * @component-example
 * <div>
 *     <!-- red square -->
 *     <sw-color-badge color="red"></sw-color-badge>
 *     <!-- green square -->
 *     <sw-color-badge color="green"></sw-color-badge>
 *     <!-- red circle -->
 *     <sw-color-badge color="red" rounded></sw-color-badge>
 * </div>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-color-badge', {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        variant: {
            type: String,
            required: false,
            default: 'default',
        },
        color: {
            type: String,
            required: false,
            default: '',
        },
        rounded: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        colorStyle() {
            if (!this.color.length) {
                return '';
            }
            return `background:${this.color}`;
        },
        variantClass() {
            return {
                [`is--${this.variant}`]: true,
                'is--rounded': this.rounded,
            };
        },
    },
});
