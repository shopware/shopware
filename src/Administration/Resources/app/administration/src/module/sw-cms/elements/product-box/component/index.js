import template from './sw-cms-el-product-box.html.twig';
import './sw-cms-el-product-box.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-product-box', {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
        Mixin.getByName('placeholder')
    ],

    computed: {
        product() {
            if (!this.element.data || !this.element.data.product) {
                return {
                    name: 'Lorem ipsum dolor',
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
                };
            }

            return this.element.data.product;
        },

        mediaUrl() {
            const context = Shopware.Context.api;

            if (this.product.cover && this.product.cover.media) {
                if (this.product.cover.media.id) {
                    return this.product.cover.media.url;
                }

                return `${context.assetsPath}${this.product.cover.media.url}`;
            }

            return `${context.assetsPath}/administration/static/img/cms/preview_glasses_large.jpg`;
        },

        altTag() {
            if (this.product.cover && this.product.cover.media && this.product.cover.media.alt) {
                return this.product.cover.media.alt;
            }

            return null;
        },

        displayModeClass() {
            if (this.element.config.displayMode.value === 'standard') {
                return null;
            }

            return `is--${this.element.config.displayMode.value}`;
        },

        verticalAlignStyle() {
            if (!this.element.config.verticalAlign || !this.element.config.verticalAlign.value) {
                return null;
            }

            return `align-content: ${this.element.config.verticalAlign.value};`;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('product-box');
            this.initElementData('product-box');
        }
    }
});
