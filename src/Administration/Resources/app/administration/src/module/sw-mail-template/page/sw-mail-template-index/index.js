import template from './sw-mail-template-index.html.twig';
import './sw-mail-template-index.scss';

/**
 * @package buyers-experience
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['acl'],

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
};
