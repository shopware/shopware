import { Component } from 'src/core/shopware';
import template from './sw-mail-template-index.html.twig';

Component.register('sw-mail-template-index', {
    template,

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    }
});
