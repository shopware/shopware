/**
 * @sw-package framework
 */

import './sw-wizard-dot-navigation.scss';
import template from './sw-wizard-dot-navigation.html.twig';

/**
 * See `sw-wizard` for an example.
 *
 * @private
 */
export default {
    template,

    props: {
        pages: {
            type: Array,
            required: true,
        },
        activePage: {
            type: Number,
            required: true,
        },
    },
};
