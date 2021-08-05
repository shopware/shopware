import template from './sw-cms-el-product-slider.html.twig';
import './sw-cms-el-product-slider.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-product-slider', {
    template,

    inject: ['feature'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    data() {
        return {
            sliderBoxLimit: 3,
        };
    },

    computed: {
        demoProductElement() {
            return {
                config: {
                    boxLayout: {
                        source: 'static',
                        value: this.element.config.boxLayout.value,
                    },
                    displayMode: {
                        source: 'static',
                        value: this.element.config.displayMode.value,
                    },
                },
                data: null,
            };
        },

        hasNavigation() {
            return !!this.element.config.navigation.value;
        },

        classes() {
            return {
                'has--navigation': this.hasNavigation,
                'has--border': !!this.element.config.border.value,
            };
        },

        sliderBoxMinWidth() {
            if (this.element.config.elMinWidth.value && this.element.config.elMinWidth.value.indexOf('px') > -1) {
                return `repeat(auto-fit, minmax(${this.element.config.elMinWidth.value}, 1fr))`;
            }

            return null;
        },

        currentDeviceView() {
            return this.cmsPageState.currentCmsDeviceView;
        },

        verticalAlignStyle() {
            if (!this.element.config.verticalAlign.value) {
                return null;
            }

            return `align-self: ${this.element.config.verticalAlign.value};`;
        },
    },

    watch: {
        'element.config.elMinWidth.value': {
            handler() {
                this.setSliderRowLimit();
            },
        },

        currentDeviceView() {
            setTimeout(() => {
                this.setSliderRowLimit();
            }, 400);
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('product-slider');
            this.initElementData('product-slider');
        },

        mountedComponent() {
            this.setSliderRowLimit();
        },

        setSliderRowLimit() {
            if (this.currentDeviceView === 'mobile' || this.$refs.productHolder.offsetWidth < 500) {
                this.sliderBoxLimit = 1;
                return;
            }

            if (!this.element.config.elMinWidth.value ||
                this.element.config.elMinWidth.value === 'px' ||
                this.element.config.elMinWidth.value.indexOf('px') === -1) {
                this.sliderBoxLimit = 3;
                return;
            }

            if (parseInt(this.element.config.elMinWidth.value.replace('px', ''), 10) <= 0) {
                return;
            }

            // Subtract to fake look in storefront which has more width
            const fakeLookWidth = 100;
            const boxWidth = this.$refs.productHolder.offsetWidth;
            const elGap = 32;
            let elWidth = parseInt(this.element.config.elMinWidth.value.replace('px', ''), 10);

            if (elWidth >= 300) {
                elWidth -= fakeLookWidth;
            }

            this.sliderBoxLimit = Math.floor(boxWidth / (elWidth + elGap));
        },

        getProductEl(product) {
            return {
                config: {
                    boxLayout: {
                        source: 'static',
                        value: this.element.config.boxLayout.value,
                    },
                    displayMode: {
                        source: 'static',
                        value: this.element.config.displayMode.value,
                    },
                },
                data: {
                    product,
                },
            };
        },
    },
});
