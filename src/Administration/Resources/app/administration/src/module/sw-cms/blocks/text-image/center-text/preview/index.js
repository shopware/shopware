import template from './sw-cms-preview-center-text.html.twig';
import './sw-cms-preview-center-text.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 * @package content
 */
Component.register('sw-cms-preview-center-text', {
    template,
});
