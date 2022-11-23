import template from './sw-cms-preview-product-listing.html.twig';
import './sw-cms-preview-product-listing.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 * @package content
 */
Component.register('sw-cms-preview-product-listing', {
    template,
});
