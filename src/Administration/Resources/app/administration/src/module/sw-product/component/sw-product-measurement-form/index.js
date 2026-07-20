/*
 * @sw-package inventory
 */
import template from './sw-product-measurement-form.html.twig';
import './sw-product-measurement-form.scss';

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
            return Shopware.Store.get('swProductDetail').product;
        },

        parentProduct() {
            return Shopware.Store.get('swProductDetail').parentProduct;
        },

        lengthUnit() {
            return Shopware.Store.get('swProductDetail').lengthUnit;
        },

        weightUnit() {
            return Shopware.Store.get('swProductDetail').weightUnit;
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

            Shopware.Store.get('swProductDetail').setLengthUnit(unit);
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
            Shopware.Store.get('swProductDetail').setWeightUnit(unit);
        },
    },
};
