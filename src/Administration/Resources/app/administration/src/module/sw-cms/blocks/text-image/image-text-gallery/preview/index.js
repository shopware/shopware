import template from './sw-cms-preview-image-text-gallery.html.twig';
import './sw-cms-preview-image-text-gallery.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-preview-image-text-gallery', {
    template,
});
