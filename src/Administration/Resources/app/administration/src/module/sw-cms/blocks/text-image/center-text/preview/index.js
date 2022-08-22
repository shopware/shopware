import template from './sw-cms-preview-center-text.html.twig';
import './sw-cms-preview-center-text.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-preview-center-text', {
    template,
});
