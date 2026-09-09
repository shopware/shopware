/*
 * @sw-package inventory
 */
import template from './sw-product-measurement-form.html.twig';
import './sw-product-measurement-form.scss';
import useSwProductDetailStore from 'shopware:stores/swProductDetail';

const { Mixin, Utils } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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

    computed: {
        product() {
            return useSwProductDetailStore().product;
        },

        parentProduct() {
            return useSwProductDetailStore().parentProduct;
        },

        lengthUnit() {
            return useSwProductDetailStore().lengthUnit;
        },

        weightUnit() {
            return useSwProductDetailStore().weightUnit;
        },

        ...mapPropertyErrors('product', [
            'width',
            'height',
            'length',
            'weight',
        ]),
    },

    methods: {
        onUpdateLengthUnit(unit, type) {
            if (type === 'width') {
                this.convertHeight(unit);
                this.convertLength(unit);
            }

            if (type === 'height') {
                this.convertWidth(unit);
                this.convertLength(unit);
            }

            if (type === 'length') {
                this.convertWidth(unit);
                this.convertHeight(unit);
            }

            useSwProductDetailStore().setLengthUnit(unit);
        },

        convertWidth(unit) {
            if (!this.product.width) {
                return;
            }

            this.product.width = Utils.unitConversion.convert(this.product.width, this.lengthUnit, unit);
        },

        convertHeight(unit) {
            if (!this.product.height) {
                return;
            }

            this.product.height = Utils.unitConversion.convert(this.product.height, this.lengthUnit, unit);
        },

        convertLength(unit) {
            if (!this.product.length) {
                return;
            }

            this.product.length = Utils.unitConversion.convert(this.product.length, this.lengthUnit, unit);
        },

        onUpdateWeightUnit(unit) {
            useSwProductDetailStore().setWeightUnit(unit);
        },
    },
};
