/**
 * @private
 * @package content
 */
export default {
    computed: {
        isProductPage() {
            return this.cmsPageState?.currentPage?.type ?? '' === 'product_detail';
        },
    },

    methods: {
        createdComponent() {
            this.initElementConfig('product-name');

            if (!this.isProductPage || this.element?.translated?.config?.content) {
                return;
            }

            if (this.element.config.content.source && this.element.config.content.value) {
                return;
            }

            this.element.config.content.source = 'mapped';
            this.element.config.content.value = 'product.name';
        },
    },
};
