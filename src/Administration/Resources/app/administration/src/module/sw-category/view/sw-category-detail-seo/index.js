import template from './sw-category-detail-seo.html.twig';
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
    },
};
