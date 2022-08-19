import template from './sw-cms-block-image-three-cover.html.twig';
import './sw-cms-block-image-three-cover.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-block-image-three-cover', {
    template,
});
