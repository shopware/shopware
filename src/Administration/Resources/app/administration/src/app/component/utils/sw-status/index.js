import template from './sw-status.html.twig';
import './sw-status.scss';

const { Component } = Shopware;

/**
 * @private
 * @description
 * Renders a status
 * @status ready
 * @example-type static
 * @component-example
 * <div>
 *     <sw-status color="gray"></sw-status>
 *     <sw-status color="green"></sw-status>
 *     <sw-status color="red"></sw-status>
 * </div>
 */
Component.register('sw-status', {
    template,

    props: {
        color: {
            type: String,
            required: false,
            default: 'green',
            validator(colorProp) {
                return [
                    'gray',
                    'blue',
                    'red',
                    'orange',
                    'green',
                ].includes(colorProp);
            },
        },
    },

    computed: {
        statusClass() {
            return {
                [`sw-status--${this.color}`]: true,
            };
        },
    },
});
