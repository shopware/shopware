import template from './sw-skeleton-bar.html.twig';
import './sw-skeleton-bar.scss';

const { Component } = Shopware;

/**
 * @private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-skeleton-bar', {
    template,
});
