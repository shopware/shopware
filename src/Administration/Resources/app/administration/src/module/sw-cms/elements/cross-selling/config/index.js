import template from './sw-cms-el-config-cross-selling.html.twig';
import './sw-cms-el-config-cross-selling.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    inject: ['repositoryFactory'],

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        Shopware() {
            return Shopware;
        },

        tabItems() {
            return [
                { label: this.$t('sw-cms.elements.general.config.tab.content'), name: 'content' },
                { label: this.$t('sw-cms.elements.general.config.tab.options'), name: 'options' },
            ];
        },

        tabPositionIdentifier() {
            return 'sw-cms-element-cross-selling';
        },

        activeTabIsExtensionTab() {
            return this.isRegisteredExtensionTab(this.activeTab);
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        productSelectContext() {
            return {
                ...Shopware.Context.api,
                inheritance: true,
            };
        },

        productCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('options.group');

            return criteria;
        },

        selectedProductCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('crossSellings.assignedProducts.product');

            return criteria;
        },

        isProductPageType() {
            return this.cmsPageState?.currentPage?.type === 'product_detail';
        },

        boxLayoutOptions() {
            return [
                {
                    id: 1,
                    value: 'standard',
                    label: this.$t('sw-cms.elements.productBox.config.label.layoutTypeStandard'),
                },
                {
                    id: 2,
                    value: 'image',
                    label: this.$t('sw-cms.elements.productBox.config.label.layoutTypeImage'),
                },
                {
                    id: 3,
                    value: 'minimal',
                    label: this.$t('sw-cms.elements.productBox.config.label.layoutTypeMinimal'),
                },
            ];
        },

        displayModeOptions() {
            return [
                {
                    id: 1,
                    value: 'standard',
                    label: this.$t('sw-cms.elements.general.config.label.displayModeStandard'),
                },
                {
                    id: 2,
                    value: 'cover',
                    label: this.$t('sw-cms.elements.general.config.label.displayModeCover'),
                },
                {
                    id: 3,
                    value: 'contain',
                    label: this.$t('sw-cms.elements.general.config.label.displayModeContain'),
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    data() {
        return {
            activeTab: 'content',
        };
    },

    methods: {
        createdComponent() {
            this.initElementConfig('cross-selling');
        },

        onNewTabActive(activeItem) {
            const activeTabName = typeof activeItem === 'string' ? activeItem : activeItem?.name;

            if (!activeTabName) {
                return;
            }

            if (!this.isCoreTab(activeTabName) && !this.isRegisteredExtensionTab(activeTabName)) {
                return;
            }

            this.activeTab = activeTabName;
        },

        isCoreTab(tabName) {
            return this.tabItems.some((tab) => tab.name === tabName);
        },

        isRegisteredExtensionTab(tabName) {
            return (Shopware.Store.get('tabs').tabItems[this.tabPositionIdentifier] ?? []).some((tab) => {
                return tab.componentSectionId === tabName;
            });
        },

        async onProductChange(productId) {
            if (productId) {
                await this.fetchProduct(productId);
            } else {
                this.deleteProduct();
            }

            this.$emit('element-update', this.element);
        },

        async fetchProduct(productId) {
            const product = await this.productRepository.get(
                productId,
                this.productSelectContext,
                this.selectedProductCriteria,
            );
            this.element.config.product.value = productId;

            this.element.data.product = product;
        },

        deleteProduct() {
            this.element.config.product.value = null;

            this.element.data.product = null;
        },
    },
};
