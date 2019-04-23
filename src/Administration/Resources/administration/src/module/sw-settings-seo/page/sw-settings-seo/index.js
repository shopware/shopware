import { Component } from 'src/core/shopware';
import template from './sw-settings-seo.html.twig';

Component.register('sw-settings-seo', {
    template,

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    methods: {
        onClickSave() {
        }
    }
});
