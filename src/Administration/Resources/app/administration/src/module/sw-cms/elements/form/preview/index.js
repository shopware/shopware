import template from './sw-cms-el-preview-form.html.twig';
import './sw-cms-el-preview-form.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 */
Component.register('sw-cms-el-preview-form', {
    template,
});
