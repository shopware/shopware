import template from './sw-landing-page-detail-cms.html.twig';
import './sw-landing-page-detail-cms.scss';

/**
 * @package content
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
            return Shopware.State.get('swCategoryDetail').landingPage;
        },

        cmsPage() {
            return Shopware.State.get('cmsPageState').currentPage;
        },
    },
};
