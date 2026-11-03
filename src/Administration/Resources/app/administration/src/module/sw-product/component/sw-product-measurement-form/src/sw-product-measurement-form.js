/*
 * @sw-package inventory
 */
import template from './sw-product-measurement-form.html.twig';
import './sw-product-measurement-form.scss';
import { convert, mapPropertyErrors, placeholderMixin, useProductDetailStore } from '../dependencies';

/**
 * @public
 */
export default {
    template,

    mixins: [placeholderMixin],

    props: {
        allowEdit: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        product() {
            return useProductDetailStore().product;
        },

        parentProduct() {
            return useProductDetailStore().parentProduct;
        },

        lengthUnit() {
            return useProductDetailStore().lengthUnit;
        },

        weightUnit() {
            return useProductDetailStore().weightUnit;
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

            useProductDetailStore().setLengthUnit(unit);
        },

        convertWidth(unit) {
            if (!this.product.width) {
                return;
            }

            this.product.width = convert(this.product.width, this.lengthUnit, unit);
        },

        convertHeight(unit) {
            if (!this.product.height) {
                return;
            }

            this.product.height = convert(this.product.height, this.lengthUnit, unit);
        },

        convertLength(unit) {
            if (!this.product.length) {
                return;
            }

            this.product.length = convert(this.product.length, this.lengthUnit, unit);
        },

        onUpdateWeightUnit(unit) {
            useProductDetailStore().setWeightUnit(unit);
        },
    },
};
