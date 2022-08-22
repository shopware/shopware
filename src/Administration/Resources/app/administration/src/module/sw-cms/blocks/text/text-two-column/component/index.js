import template from './sw-cms-block-text-two-column.html.twig';
import './sw-cms-block-text-two-column.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 */
Component.register('sw-cms-block-text-two-column', {
    template,
});
