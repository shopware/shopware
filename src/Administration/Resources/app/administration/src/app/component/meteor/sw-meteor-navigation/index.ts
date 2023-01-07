import type { RawLocation, Route } from 'vue-router';
import type { PropType } from 'vue';
import template from './sw-meteor-navigation.html.twig';
import './sw-meteor-navigation.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 */
Component.register('sw-meteor-navigation', {
    template,

    props: {
        fromLink: {
            type: Object as PropType<Route|null>,
            required: false,
            default: null,
        },
    },

    computed: {
        hasParentRoute(): boolean {
            return this.parentRoute !== null;
        },

        parentRoute(): RawLocation|Route|null {
            if (this.fromLink && this.fromLink.name !== null) {
                return this.fromLink;
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            if (typeof this.$route?.meta?.parentPath === 'string') {
                return {
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                    name: this.$route.meta.parentPath,
                };
            }

            return null;
        },
    },
});
