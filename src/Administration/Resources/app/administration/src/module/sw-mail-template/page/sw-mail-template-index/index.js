/**
 * @sw-package after-sales
 */

import template from './sw-mail-template-index.html.twig';

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl'],

    mixins: [
        Mixin.getByName('listing'),
    ],

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

        getList() {
            // handled by child components
        },
    },
};
