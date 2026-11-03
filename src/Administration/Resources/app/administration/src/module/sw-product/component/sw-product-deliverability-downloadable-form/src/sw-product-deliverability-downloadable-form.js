import template from './sw-product-deliverability-downloadable-form.html.twig';
import './sw-product-deliverability-downloadable-form.scss';
import { mapPropertyErrors, pick, placeholderMixin, useProductDetailStore } from '../dependencies';

/*
 * @sw-package inventory
 * @private
 */
/**
 * @public
 */
export default {
    template,

    mixins: [placeholderMixin],

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            enteredStock: null,
            enteredOrderQuantity: null,
            showOrderQuantitySetting: false,
        };
    },

    computed: {
        product() {
            return useProductDetailStore().product;
        },

        parentProduct() {
            return useProductDetailStore().parentProduct;
        },

        showModeSetting() {
            return useProductDetailStore().showModeSetting;
        },

        showStockSetting() {
            if (this.product.isCloseout !== null || !this.parentProduct?.id) {
                return this.product.isCloseout;
            }

            return this.parentProduct.isCloseout;
        },

        isLimitedToOneUnit() {
            return (
                (this.getInheritedValue('minPurchase') ?? 1) === 1 &&
                (this.getInheritedValue('purchaseSteps') ?? 1) === 1 &&
                this.getInheritedValue('maxPurchase') === 1
            );
        },

        ...mapPropertyErrors('product', [
            'stock',
            'deliveryTimeId',
            'isCloseout',
            'maxPurchase',
            'purchaseSteps',
            'minPurchase',
        ]),
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (typeof this.product.stock === 'undefined') {
                this.product.stock = 0;
            }

            this.showOrderQuantitySetting = !this.isLimitedToOneUnit;
        },

        onSwitchInput(enabled) {
            if (enabled === false) {
                this.enteredStock = this.product.stock;
                this.product.stock = this.product.getOrigin().stock ?? 0;

                return;
            }

            if (this.enteredStock !== null) {
                this.product.stock = this.enteredStock;
            }
        },

        onOrderQuantitySwitchInput(enabled) {
            this.showOrderQuantitySetting = enabled;

            if (enabled === false) {
                this.enteredOrderQuantity = this.getOrderQuantity();
                this.setOrderQuantity({ minPurchase: 1, purchaseSteps: 1, maxPurchase: 1 });

                return;
            }

            if (this.enteredOrderQuantity) {
                this.setOrderQuantity(this.enteredOrderQuantity);
            }
        },

        getOrderQuantity() {
            return pick(this.product, ['minPurchase', 'purchaseSteps', 'maxPurchase']);
        },

        setOrderQuantity({ minPurchase, purchaseSteps, maxPurchase }) {
            this.product.minPurchase = minPurchase;
            this.product.purchaseSteps = purchaseSteps;
            this.product.maxPurchase = maxPurchase;
        },

        getInheritedValue(field) {
            if (!this.parentProduct?.id) {
                return this.product[field];
            }

            return this.product[field] ?? this.parentProduct[field];
        },
    },
};
