import template from './sw-landing-page-detail-cms.html.twig';
import { useCategoryDetailStore, useCmsPageStore } from '../dependencies';

/**
 * @sw-package discovery
 *
 * @public
 */
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
            return useCategoryDetailStore().landingPage;
        },

        cmsPage() {
            return useCmsPageStore().currentPage;
        },
    },
};
