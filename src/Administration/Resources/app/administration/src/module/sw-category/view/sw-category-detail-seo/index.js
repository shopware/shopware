import template from './sw-category-detail-seo.html.twig';
import './sw-category-detail-seo.scss';

/**
 * @package inventory
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

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
    },
};
