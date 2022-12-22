import template from './sw-cms-block-text-hero.html.twig';
import './sw-cms-block-text-hero.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 * @package content
 */
Component.register('sw-cms-block-text-hero', {
    template,
});
