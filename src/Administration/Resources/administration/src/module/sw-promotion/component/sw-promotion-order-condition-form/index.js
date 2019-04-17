import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-promotion-order-condition-form.html.twig';
import './sw-promotion-order-condition-form.scss';

Component.register('sw-promotion-order-condition-form', {
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

        rulesStore() {
            return State.getStore('rule');
        },

        orderRulesAssociationStore() {
            return this.promotion.getAssociation('orderRules');
        }

    }

});
