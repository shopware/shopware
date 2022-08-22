import template from './sw-landing-page-view.html.twig';

const { Component, Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-landing-page-view', {
    template,

    inject: ['acl'],

    mixins: [
        Mixin.getByName('placeholder'),
    ],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
            default: false,
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
