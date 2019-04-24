import { Component, Mixin } from 'src/core/shopware';
import template from './sw-settings-country-list.html.twig';
import './sw-settings-country-list.scss';

Component.register('sw-settings-country-list', {
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
            entityName: 'country',
            sortBy: 'country.name'
        };
    }
});
