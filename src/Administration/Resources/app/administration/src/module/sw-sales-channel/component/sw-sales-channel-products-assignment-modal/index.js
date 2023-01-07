/**
 * @package sales-channel
 */

import template from './sw-sales-channel-products-assignment-modal.html.twig';
import './sw-sales-channel-products-assignment-modal.scss';

const { uniqBy } = Shopware.Utils.array;

const updateElementVisibility = (element, binding) => {
    element.style.visibility = (binding.value) ? 'visible' : 'hidden';
    element.style.position = (binding.value) ? 'static' : 'absolute';
    element.style.top = (binding.value) ? 'auto' : '0';
    element.style.left = (binding.value) ? 'auto' : '0';
    element.style.bottom = (binding.value) ? 'auto' : '0';
    element.style.right = (binding.value) ? 'auto' : '0';
    element.style.transform = (binding.value) ? 'translateX(0)' : 'translateX(100%)';
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    directives: {
        hide: {
            bind: updateElementVisibility,
            update: updateElementVisibility,
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
            singleProducts: [],
            categoryProducts: [],
            groupProducts: [],
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
            return this.products.length;
        },

        products() {
            return uniqBy([...this.singleProducts, ...this.categoryProducts, ...this.groupProducts], 'id');
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
            const cardSectionSecondaryHeight = `${this.$refs.product.$refs?.cardSectionSecondary?.$el.offsetHeight}px`;

            this.$set(this.productContainerStyle, 'grid-template-rows', `auto calc(
                ${this.tabContentHeight} - ${cardSectionSecondaryHeight}
            )`);
        },

        getCategoryContainerStyle() {
            const tabContentGutter = '20px';
            const alertHeight = `${this.$refs.category.$refs?.alert?.$el.offsetHeight}px`;
            const cardSectionSecondaryHeight = `${this.$refs.category.$refs?.cardSectionSecondary?.$el.offsetHeight}px`;

            this.$set(this.categoryContainerStyle, 'grid-template-rows', `auto calc(
                ${this.tabContentHeight} - (${tabContentGutter} + ${alertHeight} + ${cardSectionSecondaryHeight})
            )`);
        },

        getProductGroupContainerStyle() {
            const tabContentGutter = '20px';
            const alertHeight = `${this.$refs.productGroup.$refs?.alert?.$el.offsetHeight}px`;
            const cardSectionSecondaryHeight = `${this.$refs.productGroup.$refs?.cardSectionSecondary?.$el.offsetHeight}px`;

            this.$set(this.productGroupContainerStyle, 'grid-template-rows', `auto calc(
                ${this.tabContentHeight} - (${tabContentGutter} + ${alertHeight} + ${cardSectionSecondaryHeight})
            )`);
        },

        onChangeSelection(products, type) {
            this[type] = products;
        },

        onCloseModal() {
            this.$emit('modal-close');
        },

        onAddProducts() {
            this.$emit('products-add', this.products);
        },

        setProductLoading(isProductLoading) {
            this.isProductLoading = isProductLoading;
        },
    },
};
