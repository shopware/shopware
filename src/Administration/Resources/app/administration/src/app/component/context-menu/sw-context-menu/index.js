import template from './sw-context-menu.html.twig';
import './sw-context-menu.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-context-menu', {
    template,
});
