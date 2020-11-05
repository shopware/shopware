import template from './sw-category-view.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-category-view', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    inject: ['acl'],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
            default: false
        }
    },

    computed: {
        category() {
            return Shopware.State.get('swCategoryDetail').category;
        },

        cmsPage() {
            return Shopware.State.get('cmsPageState').currentPage;
        }
    }
});
