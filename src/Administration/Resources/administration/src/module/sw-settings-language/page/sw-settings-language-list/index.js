import { Component, Mixin } from 'src/core/shopware';
import template from './sw-settings-language-list.html.twig';

Component.register('sw-settings-language-list', {
    template,

    mixins: [
        Mixin.getByName('sw-settings-list')
    ],

    data() {
        return {
            entityName: 'language'
        };
    }
});
