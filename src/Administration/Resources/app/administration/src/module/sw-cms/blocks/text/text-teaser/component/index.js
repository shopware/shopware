import template from './sw-cms-block-text-teaser.html.twig';
import './sw-cms-block-text-teaser.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 * @package content
 */
Component.register('sw-cms-block-text-teaser', {
    template,
});
