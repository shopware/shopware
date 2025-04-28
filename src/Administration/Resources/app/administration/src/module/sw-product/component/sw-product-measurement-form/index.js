import convert from 'convert-units';
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

        defaultUnits: {
            type: Object,
            required: true,
        },
    },

    watch: {
        'defaultUnits.length'(newUnit, oldUnit) {
            this.syncUnits('length', newUnit, oldUnit);
        },

        'defaultUnits.width'(newUnit, oldUnit) {
            this.syncUnits('width', newUnit, oldUnit);
        },

        'defaultUnits.height'(newUnit, oldUnit) {
            this.syncUnits('height', newUnit, oldUnit);
        },
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

    methods: {
        syncUnits(changedKey, newUnit, oldUnit) {
            if (newUnit === oldUnit) {
                return;
            };

            const relatedKeys = ['length', 'width', 'height'].filter(key => key !== changedKey);

            relatedKeys.forEach((key) => {
                const oldValue = this.product[key];
                const oldKeyUnit = this.defaultUnits[key];

                if (oldValue != null && oldKeyUnit !== newUnit) {
                    try {
                        this.product[key] = convert(oldValue)
                            .from(oldKeyUnit)
                            .to(newUnit);
                    } catch (e) {
                        console.warn(`Could not convert ${key} from ${oldKeyUnit} to ${newUnit}`, e);
                    }
                }

                this.defaultUnits[key] = newUnit;
            });
        },
    }
};
