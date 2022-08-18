import template from './sw-cms-block-image-cover.html.twig';
import './sw-cms-block-image-cover.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-block-image-cover', {
    template,
});
