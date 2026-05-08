import template from './sw-landing-page-view.html.twig';

const { Mixin } = Shopware;

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl'],

    mixins: [
        Mixin.getByName('placeholder'),
    ],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
            default: false,
        },
    },

    computed: {
        Shopware() {
            return Shopware;
        },

        tabItems() {
            return [
                {
                    label: this.$t('sw-landing-page.view.general'),
                    name: 'general',
                    route: { name: 'sw.category.landingPageDetail.base' },
                    onClick: () => {
                        this.$router.push({ name: 'sw.category.landingPageDetail.base' });
                    },
                },
                {
                    label: this.$t('sw-landing-page.view.cms'),
                    name: 'cms',
                    route: { name: 'sw.category.landingPageDetail.cms' },
                    disabled: !this.acl.can('landing_page.editor'),
                    onClick: () => {
                        if (!this.acl.can('landing_page.editor')) {
                            return;
                        }

                        this.$router.push({ name: 'sw.category.landingPageDetail.cms' });
                    },
                },
            ];
        },

        defaultTabItem() {
            if (this.$route.name === 'sw.category.landingPageDetail.cms') {
                return 'cms';
            }

            return 'general';
        },

        landingPage() {
            return Shopware.Store.get('swCategoryDetail').landingPage;
        },

        cmsPage() {
            return Shopware.Store.get('cmsPage').currentPage;
        },
    },
};
