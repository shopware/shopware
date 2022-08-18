import template from './sw-cms-preview-image-three-column.html.twig';
import './sw-cms-preview-image-three-column.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-preview-image-three-column', {
    template,
});
