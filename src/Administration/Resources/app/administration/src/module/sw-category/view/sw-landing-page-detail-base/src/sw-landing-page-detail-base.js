import template from './sw-landing-page-detail-base.html.twig';
import {
    aclService,
    mapPropertyErrors,
    placeholderMixin,
    repositoryFactoryService,
    useCategoryDetailStore,
    useCmsPageStore,
} from '../dependencies';

/**
 * @sw-package discovery
 *
 * @public
 */
export default {
    template,

    inject: [repositoryFactoryService, aclService],

    mixins: [placeholderMixin],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        customFieldSetsArray() {
            return useCategoryDetailStore().customFieldSets ?? [];
        },

        ...mapPropertyErrors('landingPage', ['name', 'url', 'salesChannels']),

        landingPage() {
            return useCategoryDetailStore().landingPage;
        },

        cmsPage() {
            return useCmsPageStore().currentPage;
        },

        isLayoutSet() {
            return this.landingPage.cmsPageId !== null;
        },
    },
};
