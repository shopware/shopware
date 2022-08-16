import template from './sw-cms-preview-product-slider.html.twig';
import './sw-cms-preview-product-slider.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 */
Component.register('sw-cms-preview-product-slider', {
    template,
});
