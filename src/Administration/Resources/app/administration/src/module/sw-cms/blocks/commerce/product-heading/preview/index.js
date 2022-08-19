import template from './sw-cms-preview-product-heading.html.twig';
import './sw-cms-preview-product-heading.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-preview-product-heading', {
    template,
});
