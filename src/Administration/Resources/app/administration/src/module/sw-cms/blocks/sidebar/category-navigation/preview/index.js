import template from './sw-cms-block-preview-category-navigation.html.twig';
import './sw-cms-block-preview-category-navigation.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 * @package content
 */
Component.register('sw-cms-block-preview-category-navigation', {
    template,
});
