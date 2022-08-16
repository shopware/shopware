import template from './sw-cms-preview-text-teaser-section.html.twig';
import './sw-cms-preview-text-teaser-section.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 */
Component.register('sw-cms-preview-text-teaser-section', {
    template,
});
