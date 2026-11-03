/**
 * @sw-package checkout
 */
import template from './sw-promotion-v2-settings-trigger.html.twig';
import { aclService, Criteria } from '../dependencies';

/**
 * @public
 */
export default {
    template,

    inject: [aclService],

    props: {
        discount: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            useTrigger: this.discount.discountRules.length > 0,
            triggerType: 'single',
        };
    },

    computed: {
        /**
         * @deprecated tag:v6.8.0 - will be removed, does not offer additional filtering compared to default ruleFilter
         */
        ruleCriteria() {
            return new Criteria(1, 25).addSorting(Criteria.sort('name', 'ASC', false));
        },

        triggerOptions() {
            return this.getTriggerSelection().map((trigger) => {
                return {
                    id: trigger.value,
                    value: trigger.value,
                    label: trigger.display,
                };
            });
        },
    },

    watch: {
        'discount.discountRules'(discountRules) {
            this.discount.considerAdvancedRules = discountRules.length > 0;
        },
    },

    methods: {
        getTriggerSelection() {
            const prefix = 'sw-promotion-v2.detail.discounts.settings.trigger.triggerType';
            return [
                {
                    value: 'single',
                    display: this.$t(`${prefix}.displaySingleTrigger`),
                    disabled: false,
                },
                {
                    value: 'multi',
                    display: this.$t(`${prefix}.displayMultiTrigger`),
                    disabled: true,
                },
            ];
        },
    },
};
