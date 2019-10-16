import template from './sw-product-detail-base.html.twig';

const { Component } = Shopware;

Component.override('sw-product-detail-base', {
    template,

    methods: {
        onMainCategoryAdded(mainCategory) {
            this.product.extensions.mainCategories.push(mainCategory);
        }
    }
});
