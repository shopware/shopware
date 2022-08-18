import template from './sw-cms-block-center-text.html.twig';
import './sw-cms-block-center-text.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-block-center-text', {
    template,
});
