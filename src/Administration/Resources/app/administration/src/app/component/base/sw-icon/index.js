import template from './sw-icon.html.twig';
import legacyIconMapping from './legacy-icon-mapping';
import legacySpaceMapping from './legacy-space-mapping';
import './sw-icon.scss';

const { Component } = Shopware;
const { warn } = Shopware.Utils.debug;

/**
 * @public
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
 *     <sw-icon name="default-regular-bell" color="#f1c40f"></sw-icon>
 * </div>
 */
Component.register('sw-icon', {
    template,

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
        title: {
            type: String,
            required: false,
            default: '',
        },
        multicolor: {
            type: Boolean,
            required: false,
            default: false,
        },
        decorative: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        iconName() {
            return `icons-${this.name}`;
        },

        classes() {
            return [
                `icon--${this.name}`,
                this.multicolor ? 'sw-icon--multicolor' : 'sw-icon--fill',
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

            if (this.isLegacyName) {
                return {
                    color: this.color,
                    width: size,
                    height: size,
                };
            }

            let additionalStyles = {};

            /**
             * @deprecated tag:v6.5.0 - Icons will no longer have space around them.
             *
             * The space mapping for the old icons should be removed in the
             * next major. The space around the icons need to be set then
             * in CSS.
             */
            if (!this.feature.isActive('v6.5.0.0')) {
                const legacyName = Object.entries(legacyIconMapping).find(([oldName, newName]) => {
                    return [oldName, newName].includes(this.iconName);
                });

                if (legacyName && legacySpaceMapping[legacyName[0]]) {
                    additionalStyles = {
                        ...additionalStyles,
                        ...legacySpaceMapping[legacyName[0]],
                    };
                }
            }

            return {
                color: this.color,
                width: size,
                height: size,
                ...additionalStyles,
            };
        },

        isLegacyName() {
            return Object.keys(legacyIconMapping).includes(this.iconName);
        },

        replacementName() {
            if (!this.isLegacyName) {
                return null;
            }

            return legacyIconMapping[this.iconName];
        },

        useIconKit() {
            return this.feature.isActive('v6.5.0.0');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.color && this.multicolor) {
                warn(
                    this.$options.name,
                    `The color of "${this.name}" cannot be adjusted because it is a multicolor icon.`,
                );
            }

            // No legacy name passed
            if (!this.isLegacyName) {
                return;
            }

            // Legacy name passed and replacement available
            if (this.replacementName) {
                warn(
                    this.$options.name,
                    `The icon name "${this.name}" you provided is deprecated. Use "${this.replacementName}" instead.`,
                );

                return;
            }

            // Legacy name passed no replacement available
            warn(
                this.$options.name,
                `The icon name "${this.name}" you provided is deprecated without a replacement.`,
            );
        },
    },
});
