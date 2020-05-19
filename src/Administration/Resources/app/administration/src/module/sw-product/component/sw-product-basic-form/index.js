import template from './sw-product-basic-form.html.twig';
import './sw-product-basic-form.scss';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors, mapState } = Shopware.Component.getComponentHelper();

Component.register('sw-product-basic-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'loading'
        ]),

        ...mapPropertyErrors('product', [
            'name',
            'description',
            'productNumber',
            'manufacturerId',
            'active',
            'markAsTopseller'
        ]),

        isTitleRequired() {
            return Shopware.State.getters['context/isSystemDefaultLanguage'];
        }
    },

    methods: {
        getInheritValue(firstKey, secondKey) {
            const p = this.parentProduct;

            if (p[firstKey]) {
                return p[firstKey].hasOwnProperty(secondKey) ? p[firstKey][secondKey] : p[firstKey];
            }
            return null;
        }
    }
});
