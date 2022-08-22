import template from './sw-cms-block-image-four-column.html.twig';
import './sw-cms-block-image-four-column.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-block-image-four-column', {
    template,
});
