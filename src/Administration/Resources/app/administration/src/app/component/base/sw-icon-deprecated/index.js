/**
 * This file is not linted by ESLint because it cannot be parsed by ESLint because of the dynamic import
 * with dynamic import value.
 */
import template from './sw-icon-deprecated.html.twig';
import './sw-icon-deprecated.scss';

// Prefetch specific icons to avoid loading them asynchronously to improve performance
import '@shopware-ag/meteor-icon-kit/icons/regular/tachometer.svg';
import '@shopware-ag/meteor-icon-kit/icons/regular/products.svg';
import '@shopware-ag/meteor-icon-kit/icons/regular/shopping-bag.svg';
import '@shopware-ag/meteor-icon-kit/icons/regular/users.svg';
import '@shopware-ag/meteor-icon-kit/icons/regular/content.svg';
import '@shopware-ag/meteor-icon-kit/icons/regular/megaphone.svg';
import '@shopware-ag/meteor-icon-kit/icons/regular/plug.svg';
import '@shopware-ag/meteor-icon-kit/icons/regular/cog.svg';
import '@shopware-ag/meteor-icon-kit/icons/regular/bell.svg';
import '@shopware-ag/meteor-icon-kit/icons/regular/question-circle.svg';
import '@shopware-ag/meteor-icon-kit/icons/regular/search-s.svg';
import '@shopware-ag/meteor-icon-kit/icons/regular/chevron-down-xs.svg';
import '@shopware-ag/meteor-icon-kit/icons/regular/chevron-up-xs.svg';
import '@shopware-ag/meteor-icon-kit/icons/regular/chevron-circle-left.svg';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @description Renders an icon from the icon library.
 * @status ready
 * @example-type static
 * @component-example
 * <div>
 *     <sw-icon name="regular-circle-download" color="#1abc9c"></sw-icon>
 *     <sw-icon name="regular-storefront" color="#3498db"></sw-icon>
 *     <sw-icon name="regular-eye-slash" color="#9b59b6"></sw-icon>
 *     <sw-icon name="regular-fingerprint" color="#f39c12"></sw-icon>
 *     <sw-icon name="regular-tools-alt" color="#d35400"></sw-icon>
 *     <sw-icon name="regular-user" color="#c0392b"></sw-icon>
 *     <sw-icon name="regular-circle" color="#fc427b"></sw-icon>
 *     <sw-icon name="regular-bell" color="#f1c40f"></sw-icon>
 * </div>
 */
Component.register('sw-icon-deprecated', {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'feature',
    ],

    props: {
        name: {
            type: String,
            required: true,
        },
        color: {
            type: String,
            required: false,
            default: null,
        },
        small: {
            type: Boolean,
            required: false,
            default: false,
        },
        large: {
            type: Boolean,
            required: false,
            default: false,
        },
        size: {
            type: String,
            required: false,
            default: null,
        },
        decorative: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            iconSvgData: '',
        };
    },

    computed: {
        iconName() {
            return `icons-${this.name}`;
        },

        classes() {
            return [
                `icon--${this.name}`,
                {
                    'sw-icon--small': this.small,
                    'sw-icon--large': this.large,
                },
            ];
        },

        styles() {
            let size = this.size;

            if (!Number.isNaN(parseFloat(size)) && !Number.isNaN(size - 0)) {
                size = `${size}px`;
            }

            return {
                color: this.color,
                width: size,
                height: size,
            };
        },

        listeners() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_LISTENERS')) {
                return this.$listeners;
            }

            return {};
        },
    },

    watch: {
        name: {
            handler(newName) {
                if (!newName) {
                    return;
                }

                const [variant, ...iconName] = newName.split('-');
                this.loadIconSvgData(variant, iconName.join('-'), newName);
            },
            immediate: true,
        },
    },

    beforeMount() {
        this.iconSvgData = `<svg id="meteor-icon-kit__${this.name}"></svg>`;
    },

    methods: {
        /**
         * Loads the requested icon's SVG data.
         *
         * This defaults to loading from the meteor-icon-kit.
         *
         * This throws an exception if the import is not found. Catch this in an override to add custom icons;
         * or override and do custom logic based on the `variant`, `iconName` or `iconFullName`.
         *
         * Loosely based on an idea from https://shopwarecommunity.slack.com/archives/C04P3QBG8S2/p1683098652206189
         *
         * @return Promise for possible override fallback logic
         */
        loadIconSvgData(variant, iconName, iconFullName) {
            if (this.feature.isActive('ADMIN_VITE')) {
                // eslint-disable-next-line max-len
                return import(`./../../../../../node_modules/@shopware-ag/meteor-icon-kit/icons/${variant}/${iconName}.svg?raw`).then((iconSvgData) => {
                    if (iconSvgData.default) {
                        this.iconSvgData = iconSvgData.default;
                    } else {
                        // note this only happens if the import exists but does not export a default
                        console.error(`The SVG file for the icon name ${iconFullName} could not be found and loaded.`);
                        this.iconSvgData = '';
                    }
                });
            }

            return import(`@shopware-ag/meteor-icon-kit/icons/${variant}/${iconName}.svg`).then((iconSvgData) => {
                if (iconSvgData.default) {
                    this.iconSvgData = iconSvgData.default;
                } else {
                    // note this only happens if the import exists but does not export a default
                    console.error(`The SVG file for the icon name ${iconFullName} could not be found and loaded.`);
                    this.iconSvgData = '';
                }
            });
        },
    },
});
