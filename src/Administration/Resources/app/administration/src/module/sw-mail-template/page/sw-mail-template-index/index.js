import template from './sw-mail-template-index.html.twig';
import './sw-mail-template-index.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-mail-template-index', {
    template,

    inject: ['acl'],

    mixins: [
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            term: '',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    methods: {
        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
            this.$refs.mailHeaderFooterList.getList();
            this.$refs.mailTemplateList.getList();
        },

        onSearch(value) {
            this.term = value;
        },
    },
});
