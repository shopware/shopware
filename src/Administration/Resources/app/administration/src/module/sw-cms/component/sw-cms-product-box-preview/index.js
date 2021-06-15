import template from './sw-cms-product-box-preview.html.twig';
import './sw-cms-product-box-preview.scss';

const { Component } = Shopware;

Component.register('sw-cms-product-box-preview', {
    template,

    props: {
        hasText: {
            type: Boolean,
            default: true,
            required: false,
        },
    },
});
