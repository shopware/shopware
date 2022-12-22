import template from './sw-cms-preview-form.html.twig';
import './sw-cms-preview-form.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 * @package content
 */
Component.register('sw-cms-preview-form', {
    template,
});
