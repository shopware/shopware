/* eslint-disable indent */
import template from './sw-meteor-page.html.twig';
import './sw-meteor-page.scss';

const { Component } = Shopware;

if (Shopware.Feature.isActive('FEATURE_NEXT_12608')) {
/**
 * @private
 */
Component.register('sw-meteor-page', {
    template,

    props: {
        fullWidth: {
            type: Boolean,
            required: false,
            default: false,
        },

        hideIcon: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            module: null,
            parentRoute: null,
        };
    },

    computed: {
        pageClasses() {
            return {
                'sw-meteor-page--full-width': this.fullWidth,
            };
        },

        hasIcon() {
            return !!this.module && !!this.module.icon;
        },

        hasIconOrIconSlot() {
            return this.hasIcon || this.$slots['smart-bar-icon'] || this.$scopedSlots['smart-bar-icon'];
        },

        hasTabs() {
            return this.$slots['page-tabs'] || this.$scopedSlots['page-tabs'];
        },

        pageColor() {
            return (this.module !== null) ? this.module.color : '#d8dde6';
        },
    },

    beforeDestroy() {
        Shopware.State.dispatch('error/resetApiErrors');
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.initPage();
        },

        emitNewTab(tabItem) {
            this.$emit('new-item-active', tabItem);
        },

        initPage() {
            if (this.$route.meta.$module) {
                this.module = this.$route.meta.$module;
            }

            if (this.$route.meta.parentPath) {
                this.parentRoute = this.$route.meta.parentPath;
            }
        },
    },
});
}
