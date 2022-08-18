import template from './sw-cms-preview-product-three-column.html.twig';
import './sw-cms-preview-product-three-column.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-preview-product-three-column', {
    template,
});
