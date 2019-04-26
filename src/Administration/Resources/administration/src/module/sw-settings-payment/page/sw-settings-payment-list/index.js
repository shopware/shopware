import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-settings-payment-list.html.twig';
import './sw-settings-payment-list.scss';

Component.register('sw-settings-payment-list', {
    template,

    mixins: [
        Mixin.getByName('sw-settings-list')
    ],

    data() {
        return {
            entityName: 'payment'
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        store() {
            return State.getStore('payment_method');
        }
    }
});
