import template from './sw-cms-el-preview-image-slider.html.twig';
import './sw-cms-el-preview-image-slider.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-el-preview-image-slider', {
    template,
});
