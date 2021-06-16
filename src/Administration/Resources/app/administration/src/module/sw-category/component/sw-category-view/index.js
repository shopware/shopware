import template from './sw-category-view.html.twig';
import './sw-category-view.scss';

const { Component } = Shopware;

Component.register('sw-category-view', {
    template,

    inject: ['acl'],

    mixins: [
        'placeholder',
    ],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
            default: false,
        },
        type: {
            type: String,
            required: false,
            default: 'page',
        },
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
        },

        isPage() {
            return this.type !== 'folder' && this.type !== 'link';
        },
    },
});
