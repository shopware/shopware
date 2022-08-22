import template from './sw-cms-block-text-hero.html.twig';
import './sw-cms-block-text-hero.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-block-text-hero', {
    template,
});
