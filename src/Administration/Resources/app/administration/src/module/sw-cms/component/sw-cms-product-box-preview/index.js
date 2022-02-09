import template from './sw-cms-product-box-preview.html.twig';
import './sw-cms-product-box-preview.scss';

const { Component } = Shopware;

Component.register('sw-cms-product-box-preview', {
    template,

    props: {
        hasText: {
            type: Boolean,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
            required: false,
        },
    },
});
