import template from './sw-cms-visibility-toggle.html';
import './sw-cms-visibility-toggle.scss';

const { Component } = Shopware;

/**
 * @private
 * @package content
 */
Component.register('sw-cms-visibility-toggle', {
    template,

    inject: ['cmsService'],

    props: {
        text: {
            type: String,
            required: true,
        },
        isCollapsed: {
            type: Boolean,
            required: true,
        },
    },
});
