import template from './sw-cms-el-preview-text.html.twig';
import './sw-cms-el-preview-text.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 */
Component.register('sw-cms-el-preview-text', {
    template,
});
