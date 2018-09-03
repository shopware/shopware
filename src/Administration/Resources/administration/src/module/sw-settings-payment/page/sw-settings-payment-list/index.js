import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-settings-payment-list.html.twig';
import './sw-settings-payment-list.less';

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

    computed: {
        store() {
            return State.getStore('payment_method');
        }
    }
});
