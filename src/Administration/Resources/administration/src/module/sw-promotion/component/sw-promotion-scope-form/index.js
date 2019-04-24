import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-promotion-scope-form.html.twig';
import './sw-promotion-scope-form.scss';

Component.register('sw-promotion-scope-form', {
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
        },

        categoryStore() {
            return State.getStore('category');
        },

        productStore() {
            return State.getStore('product');
        }
    },

    methods: {
        onScopeRuleChange(ruleId) {
            this.promotion.scopeRuleId = ruleId;
        }
    }
});
