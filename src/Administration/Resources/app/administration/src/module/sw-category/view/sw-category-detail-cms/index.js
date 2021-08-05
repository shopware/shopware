import template from './sw-category-detail-cms.html.twig';
import './sw-category-detail-cms.scss';

const { Component } = Shopware;

Component.register('sw-category-detail-cms', {
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
});
