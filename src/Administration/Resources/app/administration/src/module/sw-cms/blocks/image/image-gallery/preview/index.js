import template from './sw-cms-preview-image-gallery.html.twig';
import './sw-cms-preview-image-gallery.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-preview-image-gallery', {
    template,
});
