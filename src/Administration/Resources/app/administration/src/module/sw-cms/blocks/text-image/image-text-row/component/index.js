import template from './sw-cms-block-image-text-row.html.twig';
import './sw-cms-block-image-text-row.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-block-image-text-row', {
    template,
});
