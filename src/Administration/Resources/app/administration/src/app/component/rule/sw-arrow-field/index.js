import './sw-arrow-field.scss';
import { h } from 'vue';

const { Component } = Shopware;

/**
 * @private
 * @package services-settings
 */
Component.register('sw-arrow-field', {
    compatConfig: Shopware.compatConfig,

    render() {
        return h(
            'div',
            {
                class: {
                    'sw-arrow-field': true,
                    'is--disabled': this.disabled,
                },
            },
            [
                typeof this.$slots.default === 'function' ? this.$slots.default() : this.$slots.default,
                this.getArrow(),
            ],
        );
    },
    props: {
        primary: {
            type: String,
            required: false,
            default: '#ffffff',
        },
        secondary: {
            type: String,
            required: false,
            default: '#d1d9e0',
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        arrowFill() {
            if (this.primary === '#ffffff' || !this.primary) {
                return 'transparent';
            }

            return this.primary;
        },
    },

    methods: {
        getArrow() {
            return h(
                'div',
                {
                    class: {
                        'sw-arrow-field__arrow': true,
                    },
                },
                [
                    h(
                        'svg',
                        {
                            xmlns: 'http://www.w3.org/2000/svg',
                            viewBox: '0 0 12 100',
                            preserveAspectRatio: 'none',
                        },
                        [
                            h('path', {
                                d: 'M 0 0 L 12 50 L 0 100 Z',
                                fill: this.arrowFill,
                                stroke: 'none',
                            }),
                            h('polyline', {
                                points: '0 0 12 50 0 100',
                                fill: 'none',
                                stroke: this.secondary,
                                'stroke-width': 1,
                            }),
                        ],
                    ),
                ],
            );
        },
    },
});
