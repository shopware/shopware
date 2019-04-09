import { Component } from 'src/core/shopware';
import template from './sw-settings-seo.html.twig';

Component.override('sw-settings-seo', {
    template,

    methods: {
        onSave() {
            this.$refs.seoUrlTemplateCard.onSave();
            this.$super.onSave();
        }
    }
});
