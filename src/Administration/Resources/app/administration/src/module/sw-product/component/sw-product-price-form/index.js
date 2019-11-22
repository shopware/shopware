import template from './sw-product-price-form.html.twig';
import './sw-product-price-form.scss';

const { Component, Mixin } = Shopware;
const { mapApiErrors, mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-product-price-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            displayMaintainCurrencies: false
        };
    },

    computed: {
        ...mapGetters('swProductDetail', [
            'isLoading',
            'defaultPrice',
            'defaultCurrency',
            'productTaxRate'
        ]),

        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'taxes',
            'currencies'
        ]),

        ...mapApiErrors('product', ['taxId', 'price', 'purchasePrice'])
    },

    methods: {
        removePriceInheritation(refPrice) {
            const defaultRefPrice = refPrice.find((price) => price.currencyId === this.defaultCurrency.id);

            return [{
                currencyId: defaultRefPrice.currencyId,
                gross: defaultRefPrice.gross,
                net: defaultRefPrice.net,
                linked: defaultRefPrice.linked
            }];
        },

        onMaintainCurrenciesClose(prices) {
            this.product.price = prices;

            this.displayMaintainCurrencies = false;
        }
    }
});
