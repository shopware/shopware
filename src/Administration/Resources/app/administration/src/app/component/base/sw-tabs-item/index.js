/**
 * @package admin
 */
import template from './sw-tabs-item.html.twig';
import './sw-tabs-item.scss';

const { Component } = Shopware;
const types = Shopware.Utils.types;

/**
 * @private
 * @description Renders a tab item.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-tabs>
 *
 *     <sw-tabs-item :route="{ name: 'sw.explore.index' }">
 *         Explore
 *     </sw-tabs-item>
 *
 *     <sw-tabs-item to="A link">
 *         My Plugins
 *     </sw-tabs-item>
 *
 * </sw-tabs>
 */
Component.register('sw-tabs-item', {
    template,

    compatConfig: Shopware.compatConfig,

    inheritAttrs: false,

    inject: {
        feature: {
            from: 'feature',
            default: null,
        },
        onNewItemActive: {
            from: 'onNewItemActive',
            default: null,
        },
        registerNewTabItem: {
            from: 'registerNewTabItem',
            default: null,
        },
        unregisterNewTabItem: {
            from: 'unregisterNewTabItem',
            default: null,
        },
        swTabsSetActiveItem: {
            from: 'swTabsSetActiveItem',
            default: null,
        },
    },

    props: {
        route: {
            type: [String, Object],
            required: false,
            default: '',
        },
        active: {
            type: Boolean,
            required: false,
            default: false,
        },
        activeTab: {
            type: String,
            required: false,
            default: '',
        },
        name: {
            type: String,
            required: false,
            default: '',
        },
        hasError: {
            type: Boolean,
            required: false,
            default: false,
        },
        hasWarning: {
            type: Boolean,
            required: false,
            default: false,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
        errorTooltip: {
            type: String,
            required: false,
            default() {
                return Shopware.Snippet.tc('global.sw-tabs-item.tooltipTabHasErrors');
            },
        },
        warningTooltip: {
            type: String,
            required: false,
            default() {
                return Shopware.Snippet.tc('global.sw-tabs-item.tooltipTabHasWarnings');
            },
        },
    },

    data() {
        return {
            isActive: false,
        };
    },

    computed: {
        isNative() {
            return types.isEmpty(this.route);
        },

        tabsItemClasses() {
            return {
                'sw-tabs-item--active': this.isActive,
                'sw-tabs-item--has-error': this.hasError,
                'sw-tabs-item--has-warning': !this.hasError && this.hasWarning,
                'sw-tabs-item--is-disabled': this.disabled,
            };
        },
    },

    watch: {
        '$route'() {
            this.checkIfRouteMatchesLink();
        },
    },

    mounted() {
        this.mountedComponent();
    },

    beforeUpdate() {
        this.beforeUpdateComponent();
    },

    created() {
        this.createdComponent();
    },

    beforeUnmount() {
        if (this.isCompatEnabled('INSTANCE_CHILDREN')) {
            this.$parent.$off('new-item-active', this.checkIfActive);
        } else {
            this.unregisterNewTabItem?.(this);
        }
    },

    methods: {
        createdComponent() {
            if (this.isCompatEnabled('INSTANCE_CHILDREN')) {
                this.$parent.$on('new-item-active', this.checkIfActive);
            } else {
                this.onNewItemActive?.(this.checkIfActive);
                this.registerNewTabItem?.(this);
            }

            if (this.active) {
                this.isActive = true;
            }
        },

        beforeUpdateComponent() {
            this.updateActiveState();
        },

        mountedComponent() {
            this.updateActiveState();
        },
        updateActiveState() {
            this.checkIfRouteMatchesLink();
            if (this.activeTab && this.activeTab === this.name) {
                this.isActive = true;
            }
        },

        clickEvent() {
            if (this.disabled) {
                return;
            }

            if (this.isCompatEnabled('INSTANCE_CHILDREN')) {
                this.$parent.setActiveItem(this);
            } else {
                this.swTabsSetActiveItem(this);
            }
            this.$emit('click');
        },
        checkIfActive(item) {
            this.isActive = (item.$vnode === this.$vnode);
        },
        checkIfRouteMatchesLink() {
            this.$nextTick().then(() => {
                /**
                 * Prevent endless loop with checking if the route exists. Because a router-link with a
                 * non-existing route has always the class 'router-link-active'
                 */
                let resolvedRoute;

                try {
                    resolvedRoute = this.$router.resolve(this.route);
                } catch {
                    return;
                }

                if (resolvedRoute === undefined) {
                    return;
                }

                let routeExists = false;
                routeExists = resolvedRoute.matched.length > 0;

                if (!routeExists) {
                    return;
                }

                const routeIsActive = this.$el.classList.contains('router-link-active');
                if (routeIsActive) {
                    if (this.isCompatEnabled('INSTANCE_CHILDREN')) {
                        this.$parent.setActiveItem(this);
                    } else {
                        this.swTabsSetActiveItem(this);
                    }
                }
            });
        },
    },
});
