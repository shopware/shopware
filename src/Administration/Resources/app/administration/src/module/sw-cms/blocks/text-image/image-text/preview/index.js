import template from './sw-cms-preview-image-text.html.twig';
import './sw-cms-preview-image-text.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 */
Component.register('sw-cms-preview-image-text', {
    template,
});
