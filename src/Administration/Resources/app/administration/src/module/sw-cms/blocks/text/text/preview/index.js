import template from './sw-cms-preview-text.html.twig';
import './sw-cms-preview-text.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-preview-text', {
    template,
});
