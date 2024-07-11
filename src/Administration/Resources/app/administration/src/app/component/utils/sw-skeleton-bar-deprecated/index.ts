import template from './sw-skeleton-bar-deprecated.html.twig';
import './sw-skeleton-bar.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-skeleton-bar-deprecated', {
    template,

    compatConfig: Shopware.compatConfig,
});
