import template from './sw-landing-page-view.html.twig';
import { aclService, featureService, placeholderMixin, useCategoryDetailStore, useCmsPageStore } from '../dependencies';

/**
 * @sw-package discovery
 *
 * @public
 */
export default {
    template,

    inject: [aclService, featureService],

    mixins: [placeholderMixin],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
            default: false,
        },
    },

    computed: {
        landingPage() {
            return useCategoryDetailStore().landingPage;
        },

        cmsPage() {
            return useCmsPageStore().currentPage;
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
