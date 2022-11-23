import template from './sw-cms-block-layout-config.html.twig';
import './sw-cms-block-layout-config.scss';

const { Component } = Shopware;

/**
 * @private since v6.5.0
 * @package content
 */
Component.register('sw-cms-block-layout-config', {
    template,

    inject: ['cmsService'],

    props: {
        block: {
            type: Object,
            required: true,
        },
    },
});
