import template from './sw-bulk-edit-product-media-form.html.twig';

const { Component } = Shopware;

Component.extend('sw-bulk-edit-product-media-form', 'sw-product-media-form', {
    template,

    data() {
        return {
            columnCount: 4,
            showCoverLabel: false,
        };
    },
});
