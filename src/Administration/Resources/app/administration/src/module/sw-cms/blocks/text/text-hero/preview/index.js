import template from './sw-cms-preview-text-hero.html.twig';
import './sw-cms-preview-text-hero.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 */
Component.register('sw-cms-preview-text-hero', {
    template,
});
