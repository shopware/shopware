import template from './sw-bulk-edit-product-description.html.twig';

const { Component } = Shopware;

Component.extend('sw-bulk-edit-product-description', 'sw-text-editor', {
    template,
});
