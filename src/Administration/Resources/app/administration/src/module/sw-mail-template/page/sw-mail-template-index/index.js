import template from './sw-mail-template-index.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-mail-template-index', {
    template,

    mixins: [
        Mixin.getByName('listing')
    ],

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    methods: {
        onChangeLanguage(languageId) {
            Shopware.StateDeprecated.getStore('language').setCurrentId(languageId);
            this.$refs.mailHeaderFooterList.getList();
            this.$refs.mailTemplateList.getList();
        }
    }
});
