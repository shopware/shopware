import template from './sw-cms-block-image-three-cover.html.twig';
import './sw-cms-block-image-three-cover.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 */
Component.register('sw-cms-block-image-three-cover', {
    template,
});
