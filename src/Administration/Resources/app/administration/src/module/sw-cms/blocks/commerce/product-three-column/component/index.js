import template from './sw-cms-block-product-three-column.html.twig';
import './sw-cms-block-product-three-column.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 */
Component.register('sw-cms-block-product-three-column', {
    template,
});
