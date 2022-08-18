import template from './sw-cms-block-image-highlight-row.html.twig';
import './sw-cms-block-image-highlight-row.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-block-image-highlight-row', {
    template,
});
