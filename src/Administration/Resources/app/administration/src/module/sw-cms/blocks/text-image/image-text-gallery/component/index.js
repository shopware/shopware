import template from './sw-cms-block-image-text-gallery.html.twig';
import './sw-cms-block-image-text-gallery.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-block-image-text-gallery', {
    template,
});
