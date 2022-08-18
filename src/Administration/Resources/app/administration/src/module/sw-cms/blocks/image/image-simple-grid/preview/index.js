import template from './sw-cms-preview-image-simple-grid.html.twig';
import './sw-cms-preview-image-simple-grid.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-preview-image-simple-grid', {
    template,
});
