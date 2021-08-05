import template from './sw-settings-seo.html.twig';

const { Component } = Shopware;

Component.register('sw-settings-seo', {
    template,

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    methods: {
        onClickSave() {
            this.$refs.seoUrlTemplateCard.onClickSave();
            this.$refs.systemConfig.saveAll();
        },
    },
});
