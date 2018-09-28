import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-sidebar-item.html.twig';
import './sw-sidebar-item.less';

Component.register('sw-sidebar-item', {
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

    data() {
        return {
            id: `sw-sidebar-item-${utils.createId()}`,
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
        this.componentCreated();
    },

    methods: {
        componentCreated() {
            this.$parent.registerSidebarItem(this);
        },

        openContent() {
            if (this.showContent) {
                return;
            }

            this.$emit('sw-sidebar-item-toggle-active', this);
        },

        closeContent() {
            this.isActive = false;
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
        }
    }
});
