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
        },
        type: {
            type: String,
            required: false,
            default: 'page'
        }
    },

    computed: {
        category() {
            return Shopware.State.get('swCategoryDetail').category;
        },

        cmsPage() {
            if (this.type === 'folder' || this.type === 'link') {
                return false;
            }

            return Shopware.State.get('cmsPageState').currentPage;
        }
    }
});
