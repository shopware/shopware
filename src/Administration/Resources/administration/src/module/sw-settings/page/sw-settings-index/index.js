import { Component } from 'src/core/shopware';
import template from './sw-settings-index.html.twig';
import './sw-settings-index.scss';

Component.register('sw-settings-index', {
    template,

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    }
});
