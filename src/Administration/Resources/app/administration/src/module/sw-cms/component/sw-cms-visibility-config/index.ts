import template from './sw-cms-visibility-config.html';
import './sw-cms-visibility-config.scss';

const { Component } = Shopware;

/**
 * @private
 * @package content
 */
Component.register('sw-cms-visibility-config', {
    template,

    inject: ['cmsService'],

    props: {
        visibility: {
            type: Object,
            required: true,
        },
    },
});
