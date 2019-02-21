import types from 'src/core/service/utils/types.utils';
import template from './sw-tabs-item.html.twig';
import './sw-tabs-item.scss';

/**
 * @public
 * @description Renders a tab item.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-tabs>
 *
 *     <sw-tabs-item :to="{ name: 'sw.explore.index' }">
 *         Explore
 *     </sw-tabs-item>
 *
 *     <sw-tabs-item to="A link">
 *         My Plugins
 *     </sw-tabs-item>
 *
 * </sw-tabs>
 */
export default {
    name: 'sw-tabs-item',
    template,

    inheritAttrs: false,

    props: {
        // The property "route" is deprecated and will be removed in the future. Use the new "to" property.
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
        }
    },

    data() {
        return {
            isActive: false
        };
    },

    mounted() {
        if (this.$el.classList.contains('router-link-active')) {
            this.isActive = true;
        }
        if (this.activeTab && this.activeTab === this.name) {
            this.isActive = true;
        }
    },

    created() {
        this.$parent.$on('newActiveItem', this.checkIfActive);
        if (this.active) {
            this.isActive = true;
        }
    },

    computed: {
        isNative() {
            return types.isEmpty(this.route);
        },

        tabsItemClasses() {
            return {
                'sw-tabs-item--active': this.isActive
            };
        }
    },

    methods: {
        clickEvent() {
            this.$parent.setActiveItem(this);
            this.$emit('click');
        },
        checkIfActive(item) {
            this.isActive = (item.$vnode === this.$vnode);
        }
    }
};
