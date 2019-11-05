import template from './sw-category-seo-form.html.twig';

const { Component } = Shopware;

Component.register('sw-category-seo-form', {
    template,

    props: {
        category: {
            type: Object,
            required: true
        }
    }
});
