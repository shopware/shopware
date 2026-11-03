import template from './sw-category-detail-seo.html.twig';
import { aclService, useCategoryDetailStore } from '../dependencies';

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
    },
};
