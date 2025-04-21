/**
 * @sw-package framework
 */
import template from './sw-bulk-edit-form-field-renderer.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    data() {
        return {
            defaultUnit: null,
        }
    },

    created() {
        this.defaultUnit = this.config?.defaultUnit ?? null;
    },

    computed: {
        suffixLabel() {
            return this.config?.suffixLabel ? this.config.suffixLabel : null;
        },
    },

    methods: {
        onUpdateUnit(unit) {
            if (!this.config?.measurementType) {
                return;
            }

            if (this.config.measurementType === 'length') {
                Shopware.Store.get('swProductDetail').setLengthUnit(unit);

                return;
            }

            Shopware.Store.get('swProductDetail').setMassUnit(unit);
        },
    },
};
