/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-products-assignment-modal.html.twig';
import './sw-sales-channel-products-assignment-modal.scss';

const { uniqBy } = Shopware.Utils.array;

const updateElementVisibility = (element, binding) => {
    element.style.visibility = binding.value ? 'visible' : 'hidden';
    element.style.position = binding.value ? 'static' : 'absolute';
    element.style.top = binding.value ? 'auto' : '0';
    element.style.left = binding.value ? 'auto' : '0';
    element.style.bottom = binding.value ? 'auto' : '0';
    element.style.right = binding.value ? 'auto' : '0';
    element.style.transform = binding.value ? 'translateX(0)' : 'translateX(100%)';
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['feature'],

    emits: ['modal-close', 'products-add'],

    directives: {
        hide: {
            beforeMount: updateElementVisibility,
            updated: updateElementVisibility,
        },
    },

    props: {
        salesChannel: {
            type: Object,
            required: true,
        },

        isAssignProductLoading: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            activeTab: 'singleProducts',
            singleProducts: [],
            categoryProducts: [],
            groupProducts: [],
            selections: {
                singleProducts: { ids: [], total: 0 },
                categoryProducts: { ids: [], total: 0 },
                groupProducts: { ids: [], total: 0 },
            },
            isProductLoading: false,
            tabContentHeight: '600px',
            productContainerStyle: {
                display: 'grid',
                placeItems: 'stretch',
            },
            categoryContainerStyle: {
                display: 'grid',
                placeItems: 'stretch',
            },
            productGroupContainerStyle: {
                display: 'grid',
                placeItems: 'stretch',
            },
        };
    },

    computed: {
        productCount() {
            const serverSideTotal = Object.values(this.selections)
                .filter(({ ids }) => ids.length > 0)
                .reduce((sum, { total }) => sum + total, 0);

            return this.localProducts.length + serverSideTotal;
        },

        products() {
            return uniqBy([...this.singleProducts, ...this.categoryProducts, ...this.groupProducts], 'id');
        },

        /**
         * Tabs that report category or product group ids are resolved on the server,
         * so only the products of the remaining tabs are handled by id.
         */
        localProducts() {
            const products = Object.keys(this.selections)
                .filter((type) => this.selections[type].ids.length <= 0)
                .flatMap((type) => this[type]);

            return uniqBy(products, 'id');
        },

        selection() {
            return {
                productIds: this.localProducts.map(({ id }) => id),
                categoryIds: this.selections.categoryProducts.ids,
                productStreamIds: this.selections.groupProducts.ids,
            };
        },

        productAssignmentTabs() {
            return [
                {
                    label: this.$t('sw-sales-channel.detail.productAssignmentModal.singleProducts'),
                    name: 'singleProducts',
                },
                {
                    label: this.$t('sw-sales-channel.detail.productAssignmentModal.categories.title'),
                    name: 'categories',
                },
                {
                    label: this.$t('sw-sales-channel.detail.productAssignmentModal.dynamicProductGroups.title'),
                    name: 'dynamicProductGroups',
                },
            ];
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.getProductContainerStyle();
            this.getCategoryContainerStyle();
            this.getProductGroupContainerStyle();
        },

        getProductContainerStyle() {
            const cardSectionSecondaryHeight = `${this.$refs?.product?.$refs?.cardSectionSecondary?.$el?.offsetHeight ?? 0}px`;

            this.productContainerStyle['grid-template-rows'] =
                `auto calc(${this.tabContentHeight} - ${cardSectionSecondaryHeight})`;
        },

        getCategoryContainerStyle() {
            const tabContentGutter = '20px';
            const alertHeight = `${this.$refs?.category?.$refs?.alert?.$el?.offsetHeight ?? 0}px`;
            const cardSectionSecondaryHeight = `${this.$refs?.category?.$refs?.cardSectionSecondary?.$el?.offsetHeight ?? 0}px`;

            this.categoryContainerStyle['grid-template-rows'] =
                `auto calc(${this.tabContentHeight} - (${tabContentGutter} + ${alertHeight} + ${
                    cardSectionSecondaryHeight
                }))`;
        },

        getProductGroupContainerStyle() {
            const tabContentGutter = '20px';
            const alertHeight = `${this.$refs?.productGroup?.$refs?.alert?.$el?.offsetHeight ?? 0}px`;
            const cardSectionSecondaryHeight = `${this.$refs?.productGroup?.$refs?.cardSectionSecondary?.$el?.offsetHeight ?? 0}px`;

            this.productGroupContainerStyle['grid-template-rows'] =
                `auto calc(${this.tabContentHeight} - (${tabContentGutter} + ${alertHeight} + ${
                    cardSectionSecondaryHeight
                }))`;
        },

        onChangeSelection(products, type, selection = null) {
            this[type] = products;
            this.selections[type] = selection ?? { ids: [], total: 0 };
        },

        onCloseModal() {
            this.$emit('modal-close');
        },

        onAddProducts() {
            this.$emit('products-add', this.products, this.selection);
        },

        setProductLoading(isProductLoading) {
            this.isProductLoading = isProductLoading;
        },
    },
};
