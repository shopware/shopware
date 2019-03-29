import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-promotion-discount-form.html.twig';

Component.register('sw-promotion-discount-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    props: {
        promotion: {
            type: Object,
            required: true,
            default: {}
        }
    },

    computed: {
        ruleStore() {
            return State.getStore('rule');
        }
    },

    methods: {
        onDiscountRuleChange(ruleId) {
            this.promotion.discountRuleId = ruleId;
        }
    }
});
