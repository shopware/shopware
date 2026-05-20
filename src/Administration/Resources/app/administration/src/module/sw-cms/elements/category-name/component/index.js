import './sw-cms-el-category-name.scss';

const { Mixin } = Shopware;

/**
 * @private
 * @sw-package discovery
 */
export default {
    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        isCategoryPage() {
            return this.cmsPageState?.currentPage?.type === 'product_list';
        },
    },

    methods: {
        createdComponent() {
            this.initElementConfig('category-name');

            if (this.isCategoryPage && !this.element?.translated?.config?.content) {
                this.element.config.content.source = 'mapped';
                this.element.config.content.value = 'category.name';
            }
            this.updateDemoValue();
        },

        updateDemoValue() {
            if (this.element.config.content.source === 'mapped') {
                let label = '';
                let className = 'sw-cms-el-category-name__skeleton';

                if (this.element.config.content.value === 'category.name') {
                    className = 'sw-cms-el-category-name__placeholder';
                    label = this.$t('sw-cms.elements.categoryName.label');
                }

                this.demoValue = `<h1 class="${className}">${label}</h1>`;

                if (this.cmsPageState.currentDemoEntity) {
                    const resolved = this.getDemoValue(this.element.config.content.value);

                    if (resolved) {
                        this.demoValue = `<h1>${resolved}</h1>`;
                    }
                }
            }
        },
    },
};
