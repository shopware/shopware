import template from './sw-cms-block-text-teaser-section.html.twig';
import './sw-cms-block-text-teaser-section.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 * @package content
 */
Component.register('sw-cms-block-text-teaser-section', {
    template,
});
