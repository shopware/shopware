import template from './sw-cms-preview-product-description-reviews.html.twig';
import './sw-cms-preview-product-description-reviews.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 */
Component.register('sw-cms-preview-product-description-reviews', {
    template,
});
