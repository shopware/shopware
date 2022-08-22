import template from './sw-cms-preview-form.html.twig';
import './sw-cms-preview-form.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-preview-form', {
    template,
});
