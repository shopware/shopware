/**
 * @sw-package framework
 */

import template from './sw-skip-link.html.twig';
import './sw-skip-link.scss';
import { wrapComponentConfig } from '../dependencies';

/**
 * @private - Only to be used by the Shopware Admin
 *
 * @private
 */
export default wrapComponentConfig({
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
