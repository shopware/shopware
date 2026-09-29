import template from './sw-category-detail-cms.html.twig';
import useSwCategoryDetailStore from 'shopware:stores/swCategoryDetail';
import useCmsPageStore from 'shopware:stores/cmsPage';

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
