import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-promotion-persona-form.html.twig';

Component.register('sw-promotion-persona-form', {
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

        customerStore() {
            return State.getStore('customer');
        }
    },

    methods: {
        onPersonaRuleChange(ruleId) {
            this.promotion.personaRuleId = ruleId;
        }
    }
});
