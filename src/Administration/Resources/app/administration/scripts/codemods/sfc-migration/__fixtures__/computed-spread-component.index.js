import template from './computed-spread-component.html.twig';

const { Store } = Shopware;
const { mapPropertyErrors, mapCollectionPropertyErrors, mapState } = Shopware.Component.getComponentHelper();

Shopware.Component.register('sw-computed-spread-card', {
    template,

    data() {
        return {
            product: null,
            lineItems: [],
        };
    },

    computed: {
        ...mapPropertyErrors('product', [
            'name',
            'stock',
        ]),

        ...mapCollectionPropertyErrors('lineItems', ['quantity']),

        ...mapState(() => Store.get('swProductDetail'), ['loading']),
    },
});
