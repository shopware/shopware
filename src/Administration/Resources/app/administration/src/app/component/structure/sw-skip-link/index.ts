/**
 * @package admin
 */

import template from './sw-skip-link.html.twig';
import './sw-skip-link.scss';

/**
 * @private - Only to be used by the Shopware Admin
 */
Shopware.Component.register('sw-skip-link', {
    template,

    data(): {
        focussed: boolean;
    } {
        return {
            focussed: false,
        };
    },

    methods: {
        setFocus(focussed: boolean) {
            this.focussed = focussed;
        },

        focusElement() {
            const element = window.document.getElementById('main');

            if (!element) {
                return;
            }

            element.focus();
        },
    },
});
