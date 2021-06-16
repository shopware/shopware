import template from './sw-category-detail-seo.html.twig';
import './sw-category-detail-seo.scss';

const { Component } = Shopware;

Component.register('sw-category-detail-seo', {
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
    },
});
