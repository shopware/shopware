import { Component, Mixin } from 'src/core/shopware';
import template from './sw-settings-currency-list.html.twig';

Component.register('sw-settings-currency-list', {
    template,

    mixins: [
        Mixin.getByName('sw-settings-list')
    ],

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    data() {
        return {
            entityName: 'currency',
            sortBy: 'currency.name'
        };
    }
});
