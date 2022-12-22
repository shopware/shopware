import template from './sw-cms-block-center-text.html.twig';
import './sw-cms-block-center-text.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 * @package content
 */
Component.register('sw-cms-block-center-text', {
    template,
});
