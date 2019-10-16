import template from './sw-settings-seo.html.twig';

const { Component } = Shopware;

Component.override('sw-settings-seo', {
    template,

    methods: {
        onClickSave() {
            this.$refs.seoUrlTemplateCard.onClickSave();
            this.$super('onClickSave');
        }
    }
});
