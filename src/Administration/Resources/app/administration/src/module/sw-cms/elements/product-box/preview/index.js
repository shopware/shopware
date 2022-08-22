import template from './sw-cms-el-preview-product-box.html.twig';
import './sw-cms-el-preview-product-box.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-el-preview-product-box', {
    template,
});
