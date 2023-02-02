import template from './sw-cms-block-image-text-row.html.twig';
import './sw-cms-block-image-text-row.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 */
Component.register('sw-cms-block-image-text-row', {
    template,
});
