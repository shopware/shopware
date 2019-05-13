import { Component, Mixin } from 'src/core/shopware';
import template from './sw-mail-template-index.html.twig';

Component.register('sw-mail-template-index', {
    template,

    mixins: [
        Mixin.getByName('listing')
    ],

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    }
});
