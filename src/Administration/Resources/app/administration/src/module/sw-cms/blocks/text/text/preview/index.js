import template from './sw-cms-preview-text.html.twig';
import './sw-cms-preview-text.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 * @package content
 */
Component.register('sw-cms-preview-text', {
    template,
});
