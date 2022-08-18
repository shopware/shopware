import template from './sw-cms-preview-product-listing.html.twig';
import './sw-cms-preview-product-listing.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-preview-product-listing', {
    template,
});
