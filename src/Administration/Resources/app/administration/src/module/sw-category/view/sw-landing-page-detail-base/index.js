import template from './sw-landing-page-detail-base.html.twig';
import placeholderMixin from 'shopware:mixins/placeholder';
import useSwCategoryDetailStore from 'shopware:stores/swCategoryDetail';
import useCmsPageStore from 'shopware:stores/cmsPage';

const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [placeholderMixin],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        customFieldSetsArray() {
            return useSwCategoryDetailStore().customFieldSets ?? [];
        },

        ...mapPropertyErrors('landingPage', ['name', 'url', 'salesChannels']),

        landingPage() {
            return useSwCategoryDetailStore().landingPage;
        },

        cmsPage() {
            return useCmsPageStore().currentPage;
        },

        isLayoutSet() {
            return this.landingPage.cmsPageId !== null;
        },
    },
};
