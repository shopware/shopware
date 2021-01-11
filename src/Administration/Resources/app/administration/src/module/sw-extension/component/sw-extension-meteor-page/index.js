import template from './sw-extension-meteor-page.html.twig';
import './sw-extension-meteor-page.scss';

const { Component } = Shopware;

Component.extend('sw-extension-meteor-page', 'sw-page', {
    template,

    props: {
        fullWidth: {
            type: Boolean,
            required: false,
            default: false
        },

        hideIcon: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            pageScrollOffset: 0
        };
    },

    computed: {
        pageClasses() {
            return {
                ...this.$super('pageClasses'),
                'has--head-area': false,
                'sw-page--meteor-design': true,
                'sw-page--full-width': this.fullWidth,
                'page-scrolled': this.isScrolled
            };
        },

        smartBarClasses() {
            return {
                'is--collapsed': this.isScrolled
            };
        },

        isScrolled() {
            // temporary disabled scrollbehaviour
            return false;
        },

        hasIcon() {
            return !!this.module && !!this.module.icon;
        },

        hasIconOrIconSlot() {
            return this.hasIcon || this.$slots['smart-bar-icon'] || this.$scopedSlots['smart-bar-icon'];
        },

        hasTabs() {
            return this.$slots['page-tabs'] || this.$scopedSlots['page-tabs'];
        }
    },

    mounted() {
        this.$refs.pageBody.addEventListener('scroll', this.setScrollOffset);
    },

    beforeDestroyed() {
        this.$refs.pageBody.removeEventListener('scroll', this.setScrollOffset);
    },

    methods: {
        setScrollOffset() {
            this.pageScrollOffset = this.$refs.pageBody.scrollTop;
        },

        emitNewTab(tabItem) {
            this.$emit('new-item-active', tabItem);
        }
    }
});
