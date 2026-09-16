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
        landingPage() {
            return Shopware.Store.get('swCategoryDetail').landingPage;
        },

        cmsPage() {
            return Shopware.Store.get('cmsPage').currentPage;
        },

        landingPageViewTabs() {
            const createRouteTab = (label, routeName, additionalProperties = {}) => {
                return {
                    label: this.$t(label),
                    name: routeName,
                    onClick: () => {
                        void this.$router.push({ name: routeName });
                    },
                    ...additionalProperties,
                };
            };

            return [
                createRouteTab('sw-landing-page.view.general', 'sw.category.landingPageDetail.base'),
                createRouteTab('sw-landing-page.view.cms', 'sw.category.landingPageDetail.cms', {
                    disabled: !this.acl.can('landing_page.editor'),
                }),
            ];
        },
    },
};
