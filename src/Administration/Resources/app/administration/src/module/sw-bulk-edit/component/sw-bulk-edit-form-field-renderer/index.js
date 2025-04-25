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
        const unit = this.config?.measurementType === 'length' ? 'mm' : 'kg';
        this.defaultUnit = typeof this.config.defaultUnit === 'function'
            ? this.config?.defaultUnit()
            : this.config?.defaultUnit
            ?? unit;
    },

    computed: {
        suffixLabel() {
            return this.config?.suffixLabel ? this.config.suffixLabel : null;
        },

        currentUnit: {
            set(value) {
                this.defaultUnit = value;
                if (typeof this.config.defaultUnit === 'function') {
                    this.config.defaultUnit(value);
                    return;
                }

                this.config.defaultUnit = value;
            },

            get() {
                return this.defaultUnit;
            },
        },
    },
};
