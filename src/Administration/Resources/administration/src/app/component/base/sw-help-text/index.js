import { Component } from 'src/core/shopware';
import template from './sw-help-text.html.twig';
import './sw-help-text.less';

Component.register('sw-help-text', {
    template,

    props: {
        text: {
            type: String,
            required: true,
            default: ''
        },
        width: {
            type: Number,
            required: false,
            default: 200
        },
        tooltipPosition: {
            type: String,
            required: false,
            default: 'top',
            validator(value) {
                return ['top', 'bottom', 'left', 'right'].includes(value);
            }
        },
        showDelay: {
            type: Number,
            required: false
        },
        hideDelay: {
            type: Number,
            required: false
        }
    }
});
