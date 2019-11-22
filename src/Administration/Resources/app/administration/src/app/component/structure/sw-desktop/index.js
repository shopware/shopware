import template from './sw-desktop.html.twig';
import './sw-desktop.scss';

const { Component } = Shopware;
const { hasOwnProperty } = Shopware.Utils.object;

/**
 * @private
 */
Component.register('sw-desktop', {
    template,

    data() {
        return {
            noNavigation: false
        };
    },

    computed: {
        desktopClasses() {
            return {
                'sw-desktop--no-nav': this.noNavigation
            };
        }
    },

    watch: {
        $route() {
            this.checkRouteSettings();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.checkRouteSettings();
        },

        checkRouteSettings() {
            if (this.$route.meta && hasOwnProperty(this.$route.meta, 'noNav')) {
                this.noNavigation = this.$route.meta.noNav;
            } else {
                this.noNavigation = false;
            }
        }
    }
});
