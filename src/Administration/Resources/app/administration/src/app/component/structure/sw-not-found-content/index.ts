/**
 * @sw-package framework
 */

import template from './sw-not-found-content.html.twig';
import './sw-not-found-content.scss';

/**
 * @private - Only to be used by the Shopware Admin
 * @description Renders the "page not found" state of the 404 page for unknown URLs.
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    methods: {
        goBack() {
            // Without a previous Administration page, e.g. on a direct visit, going back would leave the Administration
            if (!this.$router.options.history.state.back) {
                void this.$router.push({ name: 'sw.dashboard.index' });
                return;
            }

            this.$router.go(-1);
        },
    },
});
