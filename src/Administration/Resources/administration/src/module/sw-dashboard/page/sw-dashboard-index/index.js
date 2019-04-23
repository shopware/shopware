import { Component } from 'src/core/shopware';
import template from './sw-dashboard-index.html.twig';

Component.register('sw-dashboard-index', {
    template,

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    }
});
