import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-settings-shipping-detail-base.html.twig';

Component.register('sw-settings-shipping-detail-base', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],
    computed: {
        shippingMethodStore() {
            return State.getStore('shipping_method');
        },
        ruleStore() {
            return State.getStore('rule');
        },
        shippingMethodRuleAssociation() {
            return this.shippingMethod.getAssociation('availabilityRules');
        }
    },
    props: {
        shippingMethod: {
            type: Object,
            required: true
        }
    },
    mounted() {
        if (!this.shippingMethod.active) {
            this.shippingMethod.active = false;
        }
        if (!this.shippingMethod.bindShippingfree) {
            this.shippingMethod.bindShippingfree = false;
        }
    }
});
