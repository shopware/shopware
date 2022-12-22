import template from './sw-extension-adding-success.html.twig';
import './sw-extension-adding-success.scss';

const { Component } = Shopware;

/**
 * @package merchant-services
 * private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-extension-adding-success', {
    template,
});
