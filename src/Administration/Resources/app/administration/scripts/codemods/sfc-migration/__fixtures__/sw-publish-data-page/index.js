import template from './sw-publish-data-page.html.twig';

export default {
    template,

    data() {
        return {
            product: null,
            currentPage: null,
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Shopware.ExtensionAPI.publishData({
                id: 'sw-publish-data-page__product',
                path: 'product',
                scope: this,
            });

            Shopware.ExtensionAPI.publishData({
                id: 'sw-publish-data-page__cmsPage',
                path: 'currentPage',
                scope: this,
                deprecated: true,
                deprecationMessage: 'Use the product data set instead.',
            });
        },
    },
};
