import template from './sw-promotion-v2-settings-trigger.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-promotion-v2-settings-trigger', {
    template,

    inject: [
        'acl',
    ],

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
        ruleCriteria() {
            return (new Criteria())
                .addSorting(Criteria.sort('name', 'ASC', false));
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
            return [{
                value: 'single',
                display: this.$tc(`${prefix}.displaySingleTrigger`),
                disabled: false,
            }, {
                value: 'multi',
                display: this.$tc(`${prefix}.displayMultiTrigger`),
                disabled: true,
            }];
        },
    },
});
