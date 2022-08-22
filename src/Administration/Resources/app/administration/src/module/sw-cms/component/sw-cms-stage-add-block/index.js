import template from './sw-cms-stage-add-block.html.twig';
import './sw-cms-stage-add-block.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 */
Component.register('sw-cms-stage-add-block', {
    template,
});
