import template from './sw-product-deliverability-downloadable-form.html.twig';
import './sw-product-deliverability-downloadable-form.scss';

const { Mixin } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

/*
 * @sw-package inventory
 * @private
 */
export default {
    template,

    mixins: [Mixin.getByName('placeholder')],

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
            return Shopware.Store.get('swProductDetail').product;
        },

        parentProduct() {
            return Shopware.Store.get('swProductDetail').parentProduct;
        },

        showModeSetting() {
            return Shopware.Store.get('swProductDetail').showModeSetting;
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
            // Restoring the inheritance passes null, which keeps the stock
            if (enabled === false) {
                // Kept until saving, so switching back on restores what the merchant entered
                this.enteredStock = this.product.stock;
                // A new product has no saved stock yet and starts with 0
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
                // Kept until saving, so switching back on restores what the merchant entered
                this.enteredOrderQuantity = this.getOrderQuantity();
                this.setOrderQuantity({ minPurchase: 1, purchaseSteps: 1, maxPurchase: 1 });

                return;
            }

            if (this.enteredOrderQuantity) {
                this.setOrderQuantity(this.enteredOrderQuantity);
            }
        },

        getOrderQuantity() {
            return Shopware.Utils.object.pick(this.product, ['minPurchase', 'purchaseSteps', 'maxPurchase']);
        },

        setOrderQuantity({ minPurchase, purchaseSteps, maxPurchase }) {
            this.product.minPurchase = minPurchase;
            this.product.purchaseSteps = purchaseSteps;
            this.product.maxPurchase = maxPurchase;
        },

        // Variants inherit the order quantities of their parent until they set their own
        getInheritedValue(field) {
            if (!this.parentProduct?.id) {
                return this.product[field];
            }

            return this.product[field] ?? this.parentProduct[field];
        },
    },
};
