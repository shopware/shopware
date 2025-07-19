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

    mixins: [
        Mixin.getByName('placeholder'),
    ],

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

        ...mapPropertyErrors('product', [
            'stock',
            'deliveryTimeId',
            'isCloseout',
            'maxPurchase',
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

            this.persistedStock = this.product.stock;
        },

        onSwitchInput(event) {
            if (event === false) {
                this.product.stock = this.persistedStock;
            }
        },
    },
};
