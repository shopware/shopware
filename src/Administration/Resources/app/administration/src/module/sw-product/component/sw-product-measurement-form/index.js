import template from './sw-product-measurement-form.html.twig';
import './sw-product-measurement-form.scss';

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
        allowEdit: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            defaultUnit: 'm',
            measurementType: 'length',
        };
    },

    computed: {
        product() {
            return Shopware.Store.get('swProductDetail').product;
        },

        parentProduct() {
            return Shopware.Store.get('swProductDetail').parentProduct;
        },

        ...mapPropertyErrors('product', ['width', 'height', 'length', 'weight']),
    },
};
