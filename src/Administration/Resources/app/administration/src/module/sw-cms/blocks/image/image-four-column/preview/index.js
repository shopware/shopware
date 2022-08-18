import template from './sw-cms-preview-image-four-column.html.twig';
import './sw-cms-preview-image-four-column.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-preview-image-four-column', {
    template,
});
