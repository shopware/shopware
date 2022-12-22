import template from './sw-cms-block-product-three-column.html.twig';
import './sw-cms-block-product-three-column.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 * @package content
 */
Component.register('sw-cms-block-product-three-column', {
    template,
});
