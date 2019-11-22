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

    inheritAttrs: false,

    props: {
        route: {
            type: [String, Object],
            required: false,
            default: ''
        },
        active: {
            type: Boolean,
            required: false,
            default: false
        },
        activeTab: {
            type: String,
            required: false,
            default: ''
        },
        name: {
            type: String,
            required: false,
            default: ''
        },
        hasError: {
            type: Boolean,
            required: false,
            default: false
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            isActive: false
        };
    },

    mounted() {
        this.mountedComponent();
    },

    beforeUpdate() {
        this.beforeUpdateComponent();
    },

    watch: {
        '$route'() {
            this.checkIfRouteMatchesLink();
        }
    },

    created() {
        this.createdComponent();
    },

    computed: {
        isNative() {
            return types.isEmpty(this.route);
        },

        tabsItemClasses() {
            return {
                'sw-tabs-item--active': this.isActive,
                'sw-tabs-item--has-error': this.hasError,
                'sw-tabs-item--is-disabled': this.disabled
            };
        }
    },

    methods: {
        createdComponent() {
            this.$parent.$on('new-item-active', this.checkIfActive);
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
            this.$parent.setActiveItem(this);
            this.$emit('click');
        },
        checkIfActive(item) {
            this.isActive = (item.$vnode === this.$vnode);
        },
        checkIfRouteMatchesLink() {
            this.$nextTick().then(() => {
                const routeIsActive = this.$el.classList.contains('router-link-active');
                if (routeIsActive) {
                    this.$parent.setActiveItem(this);
                }
            });
        }
    }
});
