import template from './sw-cms-preview-product-three-column.html.twig';
import './sw-cms-preview-product-three-column.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 * @package content
 */
Component.register('sw-cms-preview-product-three-column', {
    template,
});
