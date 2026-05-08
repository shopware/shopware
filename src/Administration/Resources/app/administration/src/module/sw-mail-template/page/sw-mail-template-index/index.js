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
        Shopware() {
            return Shopware;
        },

        /**
         * Returns the search type based on active tab/route.
         */
        searchType() {
            if (this.$route.name === 'sw.mail.template.index.header_footer') {
                return 'mail_header_footer';
            }

            return 'mail_template';
        },

        tabItems() {
            return [
                {
                    label: this.$t('sw-mail-template.list.tabMailTemplates'),
                    name: 'templates',
                    route: { name: 'sw.mail.template.index.templates' },
                    onClick: () => {
                        this.$router.push({ name: 'sw.mail.template.index.templates' });
                    },
                },
                {
                    label: this.$t('sw-mail-template.list.tabHeaderFooter'),
                    name: 'header-footer',
                    route: { name: 'sw.mail.template.index.header_footer' },
                    onClick: () => {
                        this.$router.push({ name: 'sw.mail.template.index.header_footer' });
                    },
                },
            ];
        },

        defaultTabItem() {
            if (this.$route.name === 'sw.mail.template.index.header_footer') {
                return 'header-footer';
            }

            return 'templates';
        },
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    methods: {
        onChangeLanguage(languageId) {
            Shopware.Store.get('context').setApiLanguageId(languageId);

            if (Feature.isActive('V6_8_0_0')) {
                this.$refs.tabContent?.getList();
            } else {
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

        onCreateMailTemplate() {
            this.$router.push({ name: 'sw.mail.template.create' });
        },
    },
};
