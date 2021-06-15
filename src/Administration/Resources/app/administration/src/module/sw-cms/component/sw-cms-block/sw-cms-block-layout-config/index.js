import template from './sw-cms-block-layout-config.html.twig';
import './sw-cms-block-layout-config.scss';

const { Component } = Shopware;

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
