import template from './sw-category-detail-cms.html.twig';
import './sw-category-detail-cms.scss';

/**
 * @package content
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
            return Shopware.State.get('swCategoryDetail').category;
        },

        cmsPage() {
            return Shopware.State.get('cmsPageState').currentPage;
        },
    },
};
