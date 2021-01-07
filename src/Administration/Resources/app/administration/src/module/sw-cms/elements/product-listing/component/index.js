import template from './sw-cms-el-product-listing.html.twig';
import './sw-cms-el-product-listing.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-product-listing', {
    template,

    inject: ['feature'],

    mixins: [
        Mixin.getByName('cms-element')
    ],

    data() {
        return {
            demoProductCount: 8
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
                        value: 'standard'
                    }
                },
                data: this.feature.isActive('FEATURE_NEXT_10078') ? null : {
                    product: {
                        name: 'Lorem Ipsum dolor',
                        description: `Lorem ipsum dolor sit amet, consetetur sadipscing elitr,
                        sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat,
                        sed diam voluptua.`.trim(),
                        price: [
                            { gross: 19.90 }
                        ],
                        cover: {
                            media: {
                                url: '/administration/static/img/cms/preview_glasses_large.jpg',
                                alt: 'Lorem Ipsum dolor'
                            }
                        }
                    }
                }
            };
        }
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('product-listing');
        },

        mountedComponent() {
            const section = this.$el.closest('.sw-cms-section');

            if (section.classList.contains('is--sidebar')) {
                this.demoProductCount = 6;
            }
        }
    }
});
