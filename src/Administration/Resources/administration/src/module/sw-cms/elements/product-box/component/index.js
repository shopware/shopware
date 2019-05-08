import { Application, Component, Mixin } from 'src/core/shopware';
import template from './sw-cms-el-product-box.html.twig';
import './sw-cms-el-product-box.scss';

Component.register('sw-cms-el-product-box', {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
        Mixin.getByName('placeholder')
    ],

    computed: {
        product() {
            return this.element.data.product;
        },

        mediaUrl() {
            const initContainer = Application.getContainer('init');
            const context = initContainer.contextService;

            if (this.product.cover.media) {
                if (this.product.cover.media.id) {
                    return this.product.cover.media.url;
                }

                return `${context.assetsPath}${this.product.cover.media.url}`;
            }

            return `${context.assetsPath}/administration/static/img/cms/preview_glasses_large.jpg`;
        },

        displayModeClass() {
            if (this.element.config.displayMode.value === 'standard') {
                return null;
            }

            return `is--${this.element.config.displayMode.value}`;
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
