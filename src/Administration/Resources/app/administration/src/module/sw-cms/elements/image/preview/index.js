import template from './sw-cms-el-preview-image.html.twig';
import './sw-cms-el-preview-image.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 * @package content
 */
Component.register('sw-cms-el-preview-image', {
    template,
});
