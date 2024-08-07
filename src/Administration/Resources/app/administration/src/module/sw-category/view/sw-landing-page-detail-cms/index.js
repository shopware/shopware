import template from './sw-landing-page-detail-cms.html.twig';
import './sw-landing-page-detail-cms.scss';

/**
 * @package inventory
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        landingPage() {
            return Shopware.State.get('swCategoryDetail').landingPage;
        },

        cmsPage() {
            return Shopware.Store.get('cmsPageState').currentPage;
        },
    },
};
