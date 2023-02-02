import template from './sw-promotion-detail-base.html.twig';

const { Component } = Shopware;

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-promotion-detail-base', {
    template,
});
