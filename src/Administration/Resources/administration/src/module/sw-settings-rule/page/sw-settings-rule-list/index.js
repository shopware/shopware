import { Component, Mixin } from 'src/core/shopware';
import template from './sw-settings-rule-list.html.twig';

Component.register('sw-settings-rule-list', {
    template,

    mixins: [
        Mixin.getByName('sw-settings-list')
    ],

    data() {
        return {
            entityName: 'rule',
            sortBy: 'rule.name'
        };
    }
});
