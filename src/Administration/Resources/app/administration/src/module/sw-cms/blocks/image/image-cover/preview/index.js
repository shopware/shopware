import template from './sw-cms-preview-image-cover.html.twig';
import './sw-cms-preview-image-cover.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 */
Component.register('sw-cms-preview-image-cover', {
    template,
});
