import template from './sw-sidebar-item.html.twig';
import './sw-sidebar-item.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @public
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-sidebar-item
 *         title="Product"
 *         icon="regular-products"
 *         hasSimpleBadge
 *         badgeType='error'>
 *     Product in sidebar
 * </sw-sidebar-item>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-sidebar-item', {
    template,

    props: {
        title: {
            type: String,
            required: true,
        },

        icon: {
            type: String,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        position: {
            type: String,
            required: false,
            default: 'top',
            validator(value) {
                return ['top', 'bottom'].includes(value);
            },
        },

        badge: {
            type: Number,
            required: false,
            default: 0,
        },

        hasSimpleBadge: {
            type: Boolean,
            required: false,
            default: false,
        },

        badgeType: {
            type: String,
            required: false,
            default: 'info',
            validator(value) {
                return [
                    'info',
                    'warning',
                    'error',
                    'success',
                ].includes(value);
            },
        },
    },

    data() {
        return {
            isActive: false,
        };
    },

    computed: {
        sidebarItemClasses() {
            return {
                'is--active': this.showContent,
                'is--disabled': this.disabled,
            };
        },

        hasDefaultSlot() {
            return !!this.$slots.default;
        },

        showContent() {
            return this.hasDefaultSlot && this.isActive;
        },
    },

    watch: {
        disabled(newDisabledState) {
            if (newDisabledState) {
                this.closeContent();
            }
        },
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

            throw new Error('Component sw-sidebar-item must be registered as a (indirect) child of sw-sidebar');
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
    },
});
