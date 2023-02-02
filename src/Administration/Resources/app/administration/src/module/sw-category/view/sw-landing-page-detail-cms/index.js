import template from './sw-landing-page-detail-cms.html.twig';
import './sw-landing-page-detail-cms.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-landing-page-detail-cms', {
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
});
