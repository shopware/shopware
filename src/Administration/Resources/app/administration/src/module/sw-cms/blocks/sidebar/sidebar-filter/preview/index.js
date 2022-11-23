import template from './sw-cms-block-preview-sidebar-filter.html.twig';
import './sw-cms-block-preview-sidebar-filter.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 * @package content
 */
Component.register('sw-cms-block-preview-sidebar-filter', {
    template,
});
