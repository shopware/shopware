import template from './sw-category-detail-cms.html.twig';
import useCmsPageStore from 'shopware:stores/cmsPage';
import useSwCategoryDetailStore from 'shopware:stores/swCategoryDetail';

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl'],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        category() {
            return useSwCategoryDetailStore().category;
        },

        cmsPage() {
            return useCmsPageStore().currentPage;
        },
    },
};
