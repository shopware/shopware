import template from './sw-cms-el-manufacturer-logo.html.twig';

const { Mixin } = Shopware;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        isProductPage() {
            return this.cmsPageState?.currentPage?.type === 'product_detail';
        },

        styles() {
            const { displayMode, minHeight } = this.element.config;
            const isCover = displayMode.value === 'cover';

            return {
                'min-height': isCover && minHeight.value && minHeight.value !== 0 ? minHeight.value : null,
            };
        },

        logoStyles() {
            const isStandard = this.element.config.displayMode.value === 'standard';

            return {
                'max-height': isStandard ? '100px' : null,
                'align-self': this.element.config.verticalAlign?.value || null,
            };
        },
    },

    methods: {
        createdComponent() {
            this.initElementConfig('manufacturer-logo');
            this.initElementData('manufacturer-logo');

            if (this.isProductPage && !this.element?.translated?.config?.media && !this.element?.data?.media) {
                this.element.config.media.source = 'mapped';
                this.element.config.media.value = 'product.manufacturer.media';
            }
        },
    },
};
