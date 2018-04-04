import { Component } from 'src/core/shopware';
import template from './sw-context-menu-item.html.twig';
import './sw-context-menu-item.less';

Component.register('sw-context-menu-item', {
    template,

    props: {
        icon: {
            type: String,
            required: false
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false
        }
    }
});
