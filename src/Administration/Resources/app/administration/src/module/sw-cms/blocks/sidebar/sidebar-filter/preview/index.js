import template from './sw-cms-block-preview-sidebar-filter.html.twig';
import './sw-cms-block-preview-sidebar-filter.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-block-preview-sidebar-filter', {
    template,
});
