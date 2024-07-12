import template from './sw-context-menu.html.twig';
import './sw-context-menu.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 */
Component.register('sw-context-menu', {
    template,

    compatConfig: Shopware.compatConfig,
});
