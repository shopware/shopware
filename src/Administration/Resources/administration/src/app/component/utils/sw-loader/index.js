import { Component } from 'src/core/shopware';
import './sw-loader.less';
import template from './sw-loader.html.twig';

Component.register('sw-loader', {
    template,

    props: {
        size: {
            type: String,
            required: false,
            default: '50px'
        }
    }
});
