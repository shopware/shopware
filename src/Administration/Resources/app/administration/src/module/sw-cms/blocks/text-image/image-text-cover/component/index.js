import template from './sw-cms-block-image-text-cover.html.twig';
import './sw-cms-block-image-text-cover.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 * @package content
 */
Component.register('sw-cms-block-image-text-cover', {
    template,
});
