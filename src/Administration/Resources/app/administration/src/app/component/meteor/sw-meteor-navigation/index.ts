import type { RouteLocationNamedRaw } from 'vue-router';
import template from './sw-meteor-navigation.html.twig';
import './sw-meteor-navigation.scss';

/**
 * @sw-package framework
 *
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        fromLink: {
            type: Object as PropType<RouteLocationNamedRaw | null>,
            required: false,
            default: null,
        },
    },

    computed: {
        hasParentRoute(): boolean {
            return this.parentRoute !== null;
        },

        parentRoute(): RouteLocationNamedRaw | null {
            if (this.fromLink && this.fromLink.name !== null) {
                return this.fromLink;
            }

            if (typeof this.$route?.meta?.parentPath === 'string') {
                return {
                    name: this.$route.meta.parentPath,
                };
            }

            return null;
        },
    },
});
