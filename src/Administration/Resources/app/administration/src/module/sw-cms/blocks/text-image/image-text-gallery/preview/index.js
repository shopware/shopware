import template from './sw-cms-preview-image-text-gallery.html.twig';
import './sw-cms-preview-image-text-gallery.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 */
Component.register('sw-cms-preview-image-text-gallery', {
    template,
});
