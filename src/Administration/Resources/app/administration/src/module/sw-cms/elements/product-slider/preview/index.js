import template from './sw-cms-el-preview-product-slider.html.twig';
import './sw-cms-el-preview-product-slider.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 */
Component.register('sw-cms-el-preview-product-slider', {
    template,
});
