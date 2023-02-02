import template from './sw-cms-preview-image-simple-grid.html.twig';
import './sw-cms-preview-image-simple-grid.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 */
Component.register('sw-cms-preview-image-simple-grid', {
    template,
});
