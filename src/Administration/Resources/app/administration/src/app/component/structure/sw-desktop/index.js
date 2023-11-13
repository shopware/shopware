import template from './sw-desktop.html.twig';
import './sw-desktop.scss';

const { Component } = Shopware;
const { hasOwnProperty } = Shopware.Utils.object;

/**
 * @package admin
 *
 * @private
 */
Component.register('sw-desktop', {
    template,

    inject: ['feature', 'appUrlChangeService', 'userActivityApiService'],

    data() {
        return {
            noNavigation: false,
            urlDiff: null,
        };
    },

    computed: {
        desktopClasses() {
            return {
                'sw-desktop--no-nav': this.noNavigation,
            };
        },

        currentUser() {
            return Shopware.State.get('session').currentUser;
        },
    },

    watch: {
        $route() {
            this.checkRouteSettings();
        },

        '$route.name': {
            handler(to, from) {
                if (from === undefined || to === from) {
                    return;
                }

                this.onUpdateSearchFrequently();
            },
            immediate: true,
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.checkRouteSettings();
            this.updateShowUrlChangedModal();
        },

        checkRouteSettings() {
            if (this.$route.meta && hasOwnProperty(this.$route.meta, 'noNav')) {
                this.noNavigation = this.$route.meta.noNav;
            } else {
                this.noNavigation = false;
            }
        },

        updateShowUrlChangedModal() {
            if (!Shopware.State.get('context').app.config.settings.appsRequireAppUrl) {
                this.urlDiff = null;
                return;
            }

            this.appUrlChangeService.getUrlDiff().then((diff) => {
                this.urlDiff = diff;
            });
        },

        closeModal() {
            this.urlDiff = null;
        },

        onUpdateSearchFrequently() {
            const metadata = this.getModuleMetadata();

            if (!metadata || !metadata?.route?.name) {
                return false;
            }

            const data = {
                key: `${metadata.name}@${metadata.route.name}`,
                cluster: this.currentUser.id,
            };

            return this.userActivityApiService.increment(data);
        },

        getModuleMetadata() {
            const { $module } = this.$route.meta;
            const routeName = this.$route?.name;

            const { name, icon, color, entity, routes, title } = $module;

            if (!this.$te((title)) || !routes?.index) {
                return false;
            }

            // special cases with searchMatcher function at the current module
            const searchMatcher = this.getModuleMetadataWithSearchMatcher($module, routeName);
            if (searchMatcher) {
                const { components, children, meta, props, ...route } = searchMatcher.route;
                return {
                    ...searchMatcher,
                    route,
                };
            }

            if (
                routes?.index?.name === routeName ||
                routes.index?.children?.some(child => child.name === routeName)
            ) {
                const { components, children, meta, props, ...route } = routes.index;
                return {
                    name,
                    icon,
                    color,
                    title,
                    entity,
                    privilege: meta?.privilege,
                    route,
                };
            }

            if (
                routes?.create?.name === routeName ||
                routes.create?.children?.some(child => child.name === routeName)
            ) {
                const { components, children, meta, props, ...route } = routes.create;
                return {
                    name,
                    icon,
                    color,
                    entity,
                    privilege: meta?.privilege,
                    route,
                    action: true,
                };
            }

            return false;
        },

        getModuleMetadataWithSearchMatcher(module, routeName) {
            if (typeof module.searchMatcher !== 'function') {
                return false;
            }

            const { title } = module;

            // get metadata in searchMatcher
            const metadata = module.searchMatcher(
                new RegExp(`^${this.$tc(title).toLowerCase()}(.*)`),
                this.$tc(title, 2),
                module,
            );

            return metadata.find(
                item => item.route.name === routeName ||
                    item.route?.children?.some(child => child.name === routeName),
            );
        },
    },
});
