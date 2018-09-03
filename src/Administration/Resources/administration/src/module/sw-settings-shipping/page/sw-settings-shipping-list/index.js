import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-settings-shipping-list.html.twig';

Component.register('sw-settings-shipping-list', {
    template,

    mixins: [
        Mixin.getByName('sw-settings-list')
    ],

    data() {
        return {
            entityName: 'shipping'
        };
    },

    computed: {
        store() {
            return State.getStore('shipping_method');
        }
    }
});
