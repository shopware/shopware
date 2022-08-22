import template from './sw-cms-block-text-on-image.html.twig';
import './sw-cms-block-text-on-image.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-block-text-on-image', {
    template,
});
