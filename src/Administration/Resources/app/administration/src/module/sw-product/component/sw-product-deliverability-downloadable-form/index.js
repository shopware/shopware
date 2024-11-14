import template from './sw-product-deliverability-downloadable-form.html.twig';
import './sw-product-deliverability-downloadable-form.scss';

const { Mixin } = Shopware;
const { mapState, mapPropertyErrors, mapGetters } = Shopware.Component.getComponentHelper();

/*
 * @package inventory
 * @private
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

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
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'loading',
        ]),

        ...mapGetters('swProductDetail', [
            'showModeSetting',
        ]),

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
