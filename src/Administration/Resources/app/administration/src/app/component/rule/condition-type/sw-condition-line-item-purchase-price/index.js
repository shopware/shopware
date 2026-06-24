import template from './sw-condition-line-item-purchase-price.html.twig';
import './sw-condition-line-item-purchase-price.scss';

/**
 * @sw-package fundamentals@after-sales
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['feature'],

    data() {
        return {
            inputKey: 'amount',
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.addEmptyOperatorToOperatorSet(
                this.conditionDataProviderService.getOperatorSet('number'),
            );
        },

        isNetOperators() {
            return this.conditionDataProviderService.getOperatorSet('isNet');
        },

        amount: {
            get() {
                this.ensureValueExist();
                return this.condition.value.amount;
            },
            set(amount) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, amount };
            },
        },
    },

    watch: {
        operator() {
            if (this.isEmpty) {
                delete this.condition.value.amount;
            }
        },
    },
};
