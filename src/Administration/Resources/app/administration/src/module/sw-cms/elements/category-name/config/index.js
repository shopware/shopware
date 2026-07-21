/**
 * @private
 * @sw-package discovery
 */
export default {
    computed: {
        isCategoryPage() {
            return this.cmsPageState?.currentPage?.type === 'product_list';
        },
    },

    methods: {
        createdComponent() {
            this.initElementConfig('category-name');

            if (!this.isCategoryPage || this.element?.translated?.config?.content) {
                return;
            }

            if (this.element.config.content.source && this.element.config.content.value) {
                return;
            }

            this.element.config.content.source = 'mapped';
            this.element.config.content.value = 'category.name';
        },
    },
};
