import template from './sw-condition-shipping-zip-code.html.twig';
import './sw-condition-shipping-zip-code.scss';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @package business-ops
 * @description Condition for the ShippingZipCodeRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-shipping-zip-code :condition="condition" :level="0"></sw-condition-shipping-zip-code>
 */
Component.extend('sw-condition-shipping-zip-code', 'sw-condition-base', {
    template,

    data() {
        return {
            inputKey: 'zipCodes',
            isNumeric: false,
        };
    },

    computed: {
        operators() {
            if (!this.isNumeric) {
                return this.conditionDataProviderService.addEmptyOperatorToOperatorSet(
                    this.conditionDataProviderService.getOperatorSet('multiStore'),
                );
            }

            return this.conditionDataProviderService.getOperatorSet('zipCode');
        },

        zipCodes: {
            get() {
                this.ensureValueExist();

                if (!this.condition.value.zipCodes) {
                    return !this.isNumeric ? [] : null;
                }

                return !this.isNumeric ? this.condition.value.zipCodes : Number(this.condition.value.zipCodes[0]);
            },
            set(zipCodes) {
                this.ensureValueExist();

                if (!Array.isArray(zipCodes)) {
                    zipCodes = [zipCodes.toString()];
                }

                this.condition.value = { ...this.condition.value, zipCodes };
            },
        },

        taggedFieldPlaceholder() {
            const defaultPlaceholder = this.$tc('global.sw-tagged-field.text-default-placeholder');

            return `${defaultPlaceholder} ${this.$tc('global.sw-condition.condition.zipCodeWildcardPlaceholder')}`;
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.zipCodes']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueZipCodesError;
        },

        numericOptions() {
            return [
                {
                    value: false,
                    label: this.$tc('sw-property.detail.alphanumericSortingType'),
                },
                {
                    value: true,
                    label: this.$tc('sw-property.detail.numericSortingType'),
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.ensureValueExist();

            if (this.condition.value.operator !== undefined) {
                this.isNumeric = !['=', '!=', 'empty'].includes(this.condition.value.operator);
            }
        },
        onChangeNumeric(value) {
            this.ensureValueExist();

            if (value === null) {
                this.isNumeric = false;
            }

            this.condition.value.operator = undefined;
        },
    },
});
