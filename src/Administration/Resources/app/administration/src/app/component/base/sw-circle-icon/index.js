import template from './sw-circle-icon.html.twig';
import './sw-circle-icon.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @description Renders an icon from the icon library. For a list of available variants see sw-label.
 * @status ready
 * @example-type static
 * @component-example
 * @see sw-label
 * <div>
 *     <sw-circle-icon size="10" iconName="regular-cog">
 *     <sw-circle-icon size="20" variant="success" iconName="regular-checkmark">
 *     <sw-circle-icon size="30" variant="warning" iconName="regular-exclamation-triangle">
 *     <sw-circle-icon size="40" variant="error" iconName="regular-exclamation-circle">
 *     <sw-circle-icon size="50" variant="info" iconName="regular-times-hexagon">
 * </div>
 */
Component.register('sw-circle-icon', {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        size: {
            type: Number,
            required: false,
            default: 50,
            validator(value) {
                return value > 0;
            },
        },

        iconName: {
            type: String,
            required: true,
        },

        variant: {
            type: String,
            required: false,
            default: '',
            validValues: ['info', 'danger', 'success', 'warning', 'neutral', 'primary'],
        },
    },

    computed: {
        iconSize() {
            return `${this.size / 2}px`;
        },

        backgroundStyles() {
            const sizeInPx = `${this.size}px`;

            return {
                width: sizeInPx,
                height: sizeInPx,
            };
        },
    },
});
