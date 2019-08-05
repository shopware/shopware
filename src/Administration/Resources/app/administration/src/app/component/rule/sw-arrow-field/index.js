import './sw-arrow-field.scss';

const { Component } = Shopware;

Component.register('sw-arrow-field', {
    props: {
        primary: {
            type: String,
            required: false,
            default: '#ffffff'
        },
        secondary: {
            type: String,
            required: false,
            default: '#d1d9e0'
        }
    },

    render(h) {
        return h('div', {
            class: {
                'sw-arrow-field': true
            }
        }, [
            this.$slots.default,
            this.getArrow(h)
        ]);
    },

    methods: {
        getArrow(h) {
            return h('div', {
                class: {
                    'sw-arrow-field__arrow': true
                }
            }, [
                h('svg', {
                    attrs: {
                        xmlns: 'http://www.w3.org/1200/svg',
                        viewBox: '0 0 12 100',
                        preserveAspectRatio: 'none'
                    }
                }, [
                    h('path', {
                        attrs: {
                            d: 'M 0 0 L 12 50 L 0 100 Z',
                            fill: this.primary,
                            stroke: 'none'
                        }
                    }),
                    h('polyline', {
                        attrs: {
                            points: '0 0 12 50 0 100',
                            fill: 'none',
                            stroke: this.secondary,
                            'stroke-width': 1
                        }
                    })
                ])
            ]);
        }
    }
});
