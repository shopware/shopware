import { Component } from 'src/core/shopware';
import template from './sw-help-text.html.twig';
import './sw-help-text.less';

/**
 * @public
 * @description Provides a container element which is divided in multiple section with the use of CSS grid.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-help-text text="Lorem ipsum dolor sit amet, consetetur sadipscing elitr"></sw-help-text>
 */
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
            validValues: ['top', 'bottom', 'left', 'right'],
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
