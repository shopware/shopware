import { Component } from 'src/core/shopware';
import template from './sw-settings-seo.html.twig';

Component.register('sw-settings-seo', {
    template,

    methods: {
        onClickSave() {
            this.$emit('finish.save');
        }
    }
});
