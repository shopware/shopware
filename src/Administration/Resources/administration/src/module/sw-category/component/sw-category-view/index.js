import template from './sw-category-view.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-category-view', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
            default: false
        }
    },

    computed: {
        category() {
            return this.$store.state.swCategoryDetail.category;
        },

        cmsPage() {
            return this.$store.state.cmsPageState.currentPage;
        }
    }
});
