import template from './sw-cms-preview-image-cover.html.twig';
import './sw-cms-preview-image-cover.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-preview-image-cover', {
    template,
});
