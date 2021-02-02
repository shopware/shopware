import template from './sw-sidebar-filter-panel.html.twig';
import './sw-sidebar-filter-panel.scss';

const { Component } = Shopware;

/**
 * @public
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-sidebar-filter-panel title="Filter" icon="default-action-filter">
 *     Filter in sidebar
 * </sw-sidebar-filter-panel>
 */
Component.register('sw-sidebar-filter-panel', {
    template,

    props: {
        title: {
            type: String,
            required: true
        },

        icon: {
            type: String,
            required: true
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false
        },

        position: {
            type: String,
            required: false,
            default: 'top',
            validator(value) {
                return ['top', 'bottom'].includes(value);
            }
        }
    },

    watch: {
        disabled(newDisabledState) {
            if (newDisabledState) {
                this.closeContent();
            }
        }
    },

    data() {
        return {
            isActive: false
        };
    },

    computed: {
        sidebarItemClasses() {
            return {
                'is--active': this.showContent,
                'is--disabled': this.disabled
            };
        },

        hasDefaultSlot() {
            return !!this.$slots.default;
        },

        showContent() {
            return this.hasDefaultSlot && this.isActive;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            let parent = this.$parent;

            while (parent) {
                if (parent.$options.name === 'sw-sidebar') {
                    parent.registerSidebarItem(this);
                    return;
                }

                parent = parent.$parent;
            }

            throw new Error('Component sw-sidebar-filter-panel must be registered as a (indirect) child of sw-sidebar');
        },

        openContent() {
            if (this.showContent) {
                return;
            }

            this.$emit('toggle-active', this);
        },

        closeContent() {
            if (this.isActive) {
                this.isActive = false;

                this.$emit('close-content');
            }
        },

        sidebarButtonClick(sidebarItem) {
            if (this === sidebarItem) {
                this.isActive = !this.isActive;
                this.$emit('click');
                return;
            }

            if (sidebarItem.hasDefaultSlot) {
                this.isActive = false;
            }
        },

        // TODO: NEXT-12998 - Add Clear filter button to the filter panel component
        resetAll() {

        }
    }
});
