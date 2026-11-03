import template from './sw-category-detail-cms.html.twig';
import { aclService, useCategoryDetailStore, useCmsPageStore } from '../dependencies';

/**
 * @sw-package discovery
 *
 * @public
 */
export default {
    template,

    inject: [aclService],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        category() {
            return useCategoryDetailStore().category;
        },

        cmsPage() {
            return useCmsPageStore().currentPage;
        },
    },
};
