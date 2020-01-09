import template from './sw-color-badge.html.twig';
import './sw-color-badge.scss';

const { Component } = Shopware;

/**
 * @public
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
 *     <!-- white circle with black border (readability on light backgrounds) -->
 *     <sw-color-badge color="red" borderColor="black" borderWidth="1px" rounded></sw-color-badge>
 * </div>
 */
Component.register('sw-color-badge', {
    template,

    props: {
        variant: {
            type: String,
            required: false,
            default: 'default'
        },
        color: {
            type: String,
            required: false,
            default: ''
        },
        borderColor: {
            type: String,
            required: false,
            default: ''
        },
        borderWidth: {
            type: String,
            required: false,
            default: ''
        },
        borderStyle: {
            type: String,
            required: false,
            default: ''
        },
        rounded: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        colorStyle() {
            const colorSetter = this.color.length ? `background:${this.color};` : '';
            const borderColorSetter = this.borderColor.length ? `border-color:${this.borderColor};` : '';
            const borderWidthSetter = this.borderWidth.length ? `border-width:${this.borderWidth};` : '';
            let borderStyleSetter = '';

            if (this.borderColor.length && this.borderWidth.length) {
                borderStyleSetter = `border-style:${this.borderStyle.length ? this.borderStyle : 'solid'};`;
            }

            return colorSetter + borderColorSetter + borderWidthSetter + borderStyleSetter;
        },
        variantClass() {
            return {
                [`is--${this.variant}`]: true,
                'is--rounded': this.rounded
            };
        }
    }
});
