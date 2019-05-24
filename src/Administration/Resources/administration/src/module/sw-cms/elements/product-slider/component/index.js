import { Component, Mixin } from 'src/core/shopware';
import template from './sw-cms-el-product-slider.html.twig';
import './sw-cms-el-product-slider.scss';

Component.register('sw-cms-el-product-slider', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    data() {
        return {
            sliderBoxLimit: 3
        };
    },

    computed: {
        demoProductElement() {
            return {
                config: {
                    boxLayout: {
                        source: 'static',
                        value: this.element.config.boxLayout.value
                    },
                    displayMode: {
                        source: 'static',
                        value: this.element.config.displayMode.value
                    }
                },
                data: {
                    product: {
                        name: 'Lorem ipsum dolor',
                        description: `Lorem ipsum dolor sit amet, consetetur sadipscing elitr,
                    sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat,
                    sed diam voluptua.`.trim(),
                        price: {
                            gross: 19.90
                        },
                        cover: {
                            media: {
                                url: '/administration/static/img/cms/preview_glasses_large.jpg',
                                alt: 'Lorem Ipsum dolor'
                            }
                        }
                    }
                }
            };
        },

        hasNavigation() {
            return !!this.element.config.navigation.value;
        },

        classes() {
            return {
                'has--navigation': this.hasNavigation,
                'has--border': !!this.element.config.border.value
            };
        },

        sliderBoxMinWidth() {
            if (this.element.config.elMinWidth.value && this.element.config.elMinWidth.value.indexOf('px') > -1) {
                return `repeat(auto-fit, minmax(${this.element.config.elMinWidth.value}, 1fr))`;
            }

            return null;
        }
    },

    watch: {
        'element.config.elMinWidth.value': {
            handler() {
                this.setSliderRowLimit();
            }
        }
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.setSliderRowLimit();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('product-slider');
            this.initElementData('product-slider');
        },

        setSliderRowLimit() {
            if (!this.element.config.elMinWidth.value || this.element.config.elMinWidth.value.indexOf('px') === -1) {
                this.sliderBoxLimit = 3;
                return;
            }

            const boxWidth = this.$refs.productHolder.offsetWidth;
            const elWidth = parseInt(this.element.config.elMinWidth.value.replace('px', ''), 0);
            const elGap = 32;

            this.sliderBoxLimit = Math.floor(boxWidth / (elWidth + elGap),);
        },

        getProductEl(product) {
            return {
                config: {
                    boxLayout: {
                        source: 'static',
                        value: this.element.config.boxLayout.value
                    },
                    displayMode: {
                        source: 'static',
                        value: this.element.config.displayMode.value
                    }
                },
                data: {
                    product
                }
            };
        }
    }
});
