/**
 * @sw-package framework
 */

import template from './sw-not-found-content.html.twig';
import './sw-not-found-content.scss';

/**
 * @private - Only to be used by the Shopware Admin
 * @description Renders the "page not found" state, e.g. for unknown URLs or unavailable app modules.
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    methods: {
        goToDashboard() {
            void this.$router.push({ name: 'sw.dashboard.index' });
        },
    },
});
