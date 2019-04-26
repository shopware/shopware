import { Component, Mixin } from 'src/core/shopware';
import template from './sw-settings-tax-list.html.twig';

Component.register('sw-settings-tax-list', {
    template,

    mixins: [
        Mixin.getByName('sw-settings-list')
    ],

    data() {
        return {
            entityName: 'tax',
            sortBy: 'tax.name'
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    }
});
