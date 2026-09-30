import template from './sw-landing-page-detail-cms.html.twig';
import useSwCategoryDetailStore from 'shopware:stores/swCategoryDetail';
import useCmsPageStore from 'shopware:stores/cmsPage';

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        landingPage() {
            return useSwCategoryDetailStore().landingPage;
        },

        cmsPage() {
            return useCmsPageStore().currentPage;
        },
    },
};
