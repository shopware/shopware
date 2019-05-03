import { Component, Mixin } from 'src/core/shopware';
import template from './sw-cms-el-product-listing.html.twig';
import './sw-cms-el-product-listing.scss';

Component.register('sw-cms-el-product-listing', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    computed: {
        demoProductElement() {
            return {
                config: {
                    boxLayout: {
                        source: 'static',
                        value: this.element.config.boxLayout.value
                    }
                },
                data: {
                    product: {
                        name: 'Lorem Ipsum dolor',
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
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('product-listing');
        }
    }
});
