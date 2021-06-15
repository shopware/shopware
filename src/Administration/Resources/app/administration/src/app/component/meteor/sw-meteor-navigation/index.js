/* eslint-disable indent */
import template from './sw-meteor-navigation.html.twig';
import './sw-meteor-navigation.scss';

const { Component } = Shopware;

if (Shopware.Feature.isActive('FEATURE_NEXT_12608')) {
/**
 * @private
 */
Component.register('sw-meteor-navigation', {
    template,

    computed: {
        hasParentRoute() {
            return this.$route && this.$route.meta && this.$route.meta.parentPath;
        },

        parentRoute() {
            if (!this.hasParentRoute) {
                return null;
            }

            return {
                name: this.$route.meta.parentPath,
                label: this.$tc('sw-meteor.navigation.backButton'),
            };
        },
    },
});
}
