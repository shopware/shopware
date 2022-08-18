import template from './sw-cms-el-preview-image-gallery.html.twig';
import './sw-cms-el-preview-image-gallery.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-el-preview-image-gallery', {
    template,
});
