import template from './sw-sidebar.html.twig';
import './sw-sidebar.less';

/**
 * @public
 * @status ready
 * @example-type static
 * @component-example
 * <sw-sidebar slot="sidebar">
 *     <sw-sidebar-item title="Refresh" icon="default-arrow-360-left"></sw-sidebar-item>
 * </sw-sidebar>
 */
export default {
    name: 'sw-sidebar',
    template,

    props: {
        propagateWidth: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            items: [],
            isOpened: false,
            _parent: this.$parent
        };
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    computed: {
        sections() {
            const sections = {};
            this.items.forEach((item) => {
                if (!sections[item.position]) {
                    sections[item.position] = [];
                }
                sections[item.position].push(item);
            });

            return sections;
        },

        sidebarClasses() {
            return {
                'is--opened': this.isOpened
            };
        }
    },

    methods: {
        createdComponent() {
            let parent = this.$parent;

            while (parent) {
                if (parent.$options.name === 'sw-page') {
                    this._parent = parent;
                    return;
                }

                parent = parent.$parent;
            }
        },

        mountedComponent() {
            if (this.propagateWidth) {
                const sidebarWidth = this.$el.querySelector('.sw-sidebar__navigation').offsetWidth;

                this._parent.$emit('sw-sidebar-mounted', sidebarWidth);
            }
        },

        destroyedComponent() {
            if (this.propagateWidth) {
                this._parent.$emit('sw-sidebar-destroyed');
            }
        },

        _isItemRegistered(itemToCheck) {
            const index = this.items.findIndex((item) => {
                return item === itemToCheck;
            });
            return index > -1;
        },

        _isAnyItemActive() {
            const index = this.items.findIndex((item) => {
                return item.isActive;
            });
            return index > -1;
        },

        closeSidebar() {
            this.isOpened = false;
        },

        registerSidebarItem(item) {
            if (this._isItemRegistered(item)) {
                return;
            }

            this.items.push(item);

            this.$on('sw-sidebar-navigation-item-clicked', item.sidebarButtonClick);
            item.$on('sw-sidebar-item-toggle-active', this.setItemActive);
            item.$on('sw-sidebar-item-close-content', this.closeSidebar);
        },

        setItemActive(clickedItem) {
            this.$emit('sw-sidebar-navigation-item-clicked', clickedItem);

            if (clickedItem.hasDefaultSlot) {
                this.isOpened = this._isAnyItemActive();
            }
        }
    }
};
