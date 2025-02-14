import template from './sw-mail-template-index.html.twig';
import './sw-mail-template-index.scss';

/**
 * @sw-package after-sales
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

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
            Shopware.Store.get('context').setApiLanguageId(languageId);
            this.$refs.mailHeaderFooterList.getList();
            this.$refs.mailTemplateList.getList();
        },

        onSearch(value) {
            this.term = value;
        },
    },
};
