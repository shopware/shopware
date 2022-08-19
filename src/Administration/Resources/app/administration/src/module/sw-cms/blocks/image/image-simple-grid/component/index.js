import template from './sw-cms-block-image-simple-grid.html.twig';
import './sw-cms-block-image-simple-grid.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-block-image-simple-grid', {
    template,
});
