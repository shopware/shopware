import template from './sw-landing-page-view.html.twig';

const { Mixin } = Shopware;

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'feature',
    ],

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
        tabs() {
            const isCmsTabDisabled = !this.acl.can('landing_page.editor');

            return [
                {
                    label: this.$t('sw-landing-page.view.general'),
                    name: 'sw.category.landingPageDetail.base',
                    onClick: () => {
                        void this.$router.push({ name: 'sw.category.landingPageDetail.base' });
                    },
                },
                {
                    label: this.$t('sw-landing-page.view.cms'),
                    name: 'sw.category.landingPageDetail.cms',
                    disabled: isCmsTabDisabled,
                    onClick: () => {
                        if (isCmsTabDisabled) {
                            return;
                        }

                        void this.$router.push({ name: 'sw.category.landingPageDetail.cms' });
                    },
                },
            ];
        },

        landingPage() {
            return Shopware.Store.get('swCategoryDetail').landingPage;
        },

        cmsPage() {
            return Shopware.Store.get('cmsPage').currentPage;
        },
    },
};
