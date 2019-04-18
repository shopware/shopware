import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-promotion-persona-form.html.twig';
import './sw-promotion-persona-form.scss';

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
        },
        personaRulesAssociationStore() {
            return this.promotion.getAssociation('personaRules');
        },
        personaCustomerAssociationStore() {
            return this.promotion.getAssociation('personaCustomers');
        }
    }
});
