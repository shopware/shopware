import './sw-arrow-field.scss';

const { Component } = Shopware;

/**
 * @private
 * @package services-settings
 */
Component.register('sw-arrow-field', {
    compatConfig: Shopware.compatConfig,

    render(h) {
        return h('div', {
            class: {
                'sw-arrow-field': true,
                'is--disabled': this.disabled,
            },
        }, [
            this.$slots.default,
            this.getArrow(h),
        ]);
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
        getArrow(h) {
            return h('div', {
                class: {
                    'sw-arrow-field__arrow': true,
                },
            }, [
                h('svg', {
                    attrs: {
                        xmlns: 'http://www.w3.org/1200/svg',
                        viewBox: '0 0 12 100',
                        preserveAspectRatio: 'none',
                    },
                }, [
                    h('path', {
                        attrs: {
                            d: 'M 0 0 L 12 50 L 0 100 Z',
                            fill: this.arrowFill,
                            stroke: 'none',
                        },
                    }),
                    h('polyline', {
                        attrs: {
                            points: '0 0 12 50 0 100',
                            fill: 'none',
                            stroke: this.secondary,
                            'stroke-width': 1,
                        },
                    }),
                ]),
            ]);
        },
    },
});
