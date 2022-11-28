import template from './sw-cms-toolbar.html.twig';
import './sw-cms-toolbar.scss';

const { Component } = Shopware;

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-toolbar', {
    template,
});
