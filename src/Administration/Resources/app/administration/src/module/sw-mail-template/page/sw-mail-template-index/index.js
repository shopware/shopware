import template from './sw-mail-template-index.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-mail-template-index', {
    template,

    mixins: [
        Mixin.getByName('listing')
    ],

    inject: ['acl'],

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    methods: {
        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
            this.$refs.mailHeaderFooterList.getList();
            this.$refs.mailTemplateList.getList();
        }
    }
});
