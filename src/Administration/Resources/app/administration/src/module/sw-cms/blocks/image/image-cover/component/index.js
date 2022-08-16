import template from './sw-cms-block-image-cover.html.twig';
import './sw-cms-block-image-cover.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 */
Component.register('sw-cms-block-image-cover', {
    template,
});
