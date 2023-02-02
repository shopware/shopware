import template from './sw-help-text.html.twig';
import './sw-help-text.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @public
 * @description The help text adds a question mark icon which triggers a tooltip with your desired content.
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
            default: '',
        },
        width: {
            type: Number,
            required: false,
            default: 200,
        },
        tooltipPosition: {
            type: String,
            required: false,
            default: 'top',
            validValues: ['top', 'bottom', 'left', 'right'],
            validator(value) {
                return ['top', 'bottom', 'left', 'right'].includes(value);
            },
        },
        showDelay: {
            type: Number,
            required: false,
            default: 100,
        },
        hideDelay: {
            type: Number,
            required: false,
            default: 100,
        },
    },
});
