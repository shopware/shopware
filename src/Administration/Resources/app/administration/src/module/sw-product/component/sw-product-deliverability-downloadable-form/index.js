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
            persistedStock: null,
            showOrderQuantitySetting: false,
            orderQuantityBeforeSwitchOff: null,
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

        ...mapPropertyErrors('product', [
            'stock',
            'deliveryTimeId',
            'isCloseout',
            'maxPurchase',
            'purchaseSteps',
            'minPurchase',
        ]),
    },

    watch: {
        // The product is reloaded after saving, and a variant's parent is loaded after the variant itself
        product() {
            this.initOrderQuantitySetting();
        },

        parentProduct() {
            this.initOrderQuantitySetting();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (typeof this.product.stock === 'undefined') {
                this.product.stock = 0;
            }

            this.persistedStock = this.product.stock;
            this.initOrderQuantitySetting();
        },

        onSwitchInput(event) {
            if (event === false) {
                this.product.stock = this.persistedStock;
            }
        },

        initOrderQuantitySetting() {
            this.showOrderQuantitySetting = this.allowsMultipleUnits();
            this.orderQuantityBeforeSwitchOff = null;
        },

        allowsMultipleUnits() {
            return (
                this.getOrderQuantity('maxPurchase') !== 1 ||
                (this.getOrderQuantity('minPurchase') ?? 1) > 1 ||
                (this.getOrderQuantity('purchaseSteps') ?? 1) > 1
            );
        },

        getOrderQuantity(field) {
            if (!this.parentProduct?.id) {
                return this.product[field];
            }

            return this.product[field] ?? this.parentProduct[field];
        },

        onOrderQuantitySwitchInput(enabled) {
            this.showOrderQuantitySetting = enabled;

            if (!enabled) {
                // Kept until saving, so switching back on restores what the merchant entered
                this.orderQuantityBeforeSwitchOff = {
                    minPurchase: this.product.minPurchase,
                    purchaseSteps: this.product.purchaseSteps,
                    maxPurchase: this.product.maxPurchase,
                };

                this.product.minPurchase = 1;
                this.product.purchaseSteps = 1;
                this.product.maxPurchase = 1;

                return;
            }

            if (this.orderQuantityBeforeSwitchOff) {
                this.product.minPurchase = this.orderQuantityBeforeSwitchOff.minPurchase;
                this.product.purchaseSteps = this.orderQuantityBeforeSwitchOff.purchaseSteps;
                this.product.maxPurchase = this.orderQuantityBeforeSwitchOff.maxPurchase;
                this.orderQuantityBeforeSwitchOff = null;

                return;
            }

            // Lift the limit of one unit: products fall back to the maximum quantity of the cart settings,
            // variants to the max. order quantity of their parent
            if (this.product.maxPurchase === 1) {
                this.product.maxPurchase = null;
            }
        },
    },
};
