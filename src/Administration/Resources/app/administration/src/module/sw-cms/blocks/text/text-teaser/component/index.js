import template from './sw-cms-block-text-teaser.html.twig';
import './sw-cms-block-text-teaser.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-block-text-teaser', {
    template,
});
