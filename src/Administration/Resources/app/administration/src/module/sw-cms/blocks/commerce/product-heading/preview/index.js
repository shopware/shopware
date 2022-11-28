import template from './sw-cms-preview-product-heading.html.twig';
import './sw-cms-preview-product-heading.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 * @package content
 */
Component.register('sw-cms-preview-product-heading', {
    template,
});
