import template from './sw-product-basic-form.html.twig';
import './sw-product-basic-form.scss';

const { Component, Mixin, StateDeprecated } = Shopware;
const { mapApiErrors, mapState } = Shopware.Component.getComponentHelper();

Component.register('sw-product-basic-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            isTitleRequired: true
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'loading'
        ]),

        ...mapApiErrors('product', ['name', 'description', 'productNumber', 'manufacturerId', 'active', 'markAsTopseller']),

        languageStore() {
            return StateDeprecated.getStore('language');
        }
    },

    watch: {
        product: {
            handler() {
                this.updateIsTitleRequired();
            },
            immediate: true,
            deep: true
        }
    },

    methods: {
        updateIsTitleRequired() {
            // TODO: Refactor when there is a possibility to check if the title field is inherited
            this.isTitleRequired = this.languageStore.getCurrentLanguage().id === Shopware.Defaults.systemLanguageId;
        },

        getInheritValue(firstKey, secondKey) {
            const p = this.parentProduct;

            if (p[firstKey]) {
                return p[firstKey].hasOwnProperty(secondKey) ? p[firstKey][secondKey] : p[firstKey];
            }
            return null;
        }
    }
});
