import template from './sw-cms-preview-text-teaser-section.html.twig';
import './sw-cms-preview-text-teaser-section.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-preview-text-teaser-section', {
    template,
});
