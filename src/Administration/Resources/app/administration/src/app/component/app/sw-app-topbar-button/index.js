/**
 * @sw-package innovation
 */

import template from './sw-app-topbar-button.html.twig';
import './sw-app-topbar-button.scss';
import useTopBarButtonStore from 'shopware:stores/topBarButton';

/**
 * @private
 * @description Apply for upselling service only, no public usage
 */
export default {
    template,

    computed: {
        topBarButtons() {
            return useTopBarButtonStore().buttons;
        },
    },

    methods: {
        async runAction(button) {
            if (typeof button.callback !== 'function') {
                return;
            }

            button.callback();
        },
    },
};
