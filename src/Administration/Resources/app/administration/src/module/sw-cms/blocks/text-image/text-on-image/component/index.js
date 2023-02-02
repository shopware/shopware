import template from './sw-cms-block-text-on-image.html.twig';
import './sw-cms-block-text-on-image.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 */
Component.register('sw-cms-block-text-on-image', {
    template,
});
