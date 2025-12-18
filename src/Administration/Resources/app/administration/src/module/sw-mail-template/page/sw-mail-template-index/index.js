/**
 * @sw-package after-sales
 */

import template from './sw-mail-template-index.html.twig';

const { Mixin, Feature } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl'],

    mixins: [
        /**
         * @deprecated tag:v6.8.0 - Will be removed.
         */
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            /**
             * @deprecated tag:v6.8.0 - Will be removed together with listing mixin.
             */
            term: '',
        };
    },

    computed: {
        /**
         * @deprecated tag:v6.8.0 - Will be removed.
         */
        useTabs() {
            return Feature.isActive('V6_8_0_0');
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed.
         */
        currentSearchTerm() {
            return this.$route.query.term || '';
        },
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    methods: {
        /**
         * @deprecated tag:v6.8.0 - The if block will be removed.
         */
        onChangeLanguage(languageId) {
            Shopware.Store.get('context').setApiLanguageId(languageId);

            if (!this.useTabs) {
                this.$refs.mailHeaderFooterList?.getList();
                this.$refs.mailTemplateList?.getList();
            }
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed together with listing mixin.
         */
        getList() {
            // Required by listing mixin
        },
    },
};
