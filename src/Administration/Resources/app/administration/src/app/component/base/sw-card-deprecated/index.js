import template from './sw-card-deprecated.html.twig';
import './sw-card-deprecated.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @description A card is a flexible and extensible content container.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-card title="Test title" subtitle="Test subtitle">
 *     Your content
 * </sw-card>
 */
Component.register('sw-card-deprecated', {
    template,
    inheritAttrs: false,

    inject: ['feature'],

    props: {
        positionIdentifier: {
            type: String,
            required: true,
            default: null,
        },
        title: {
            type: String,
            required: false,
            default: '',
        },
        subtitle: {
            type: String,
            required: false,
            default: '',
        },
        hero: {
            type: Boolean,
            required: false,
            default: false,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
        large: {
            type: Boolean,
            required: false,
            default: false,
        },
        aiBadge: {
            type: Boolean,
            required: false,
            default: false,
        },
        contentPadding: {
            type: Boolean,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    computed: {
        showHeader() {
            return !!this.title
                || !!this.$slots.title
                || !!this.$scopedSlots.title
                || !!this.subtitle
                || !!this.$slots.subtitle
                || !!this.$scopedSlots.subtitle
                || !!this.$slots.avatar
                || !!this.$scopedSlots.avatar;
        },

        hasAvatar() {
            return !!this.$slots.avatar || !!this.$scopedSlots.avatar;
        },

        cardContentClasses() {
            return {
                'no--padding': !this.contentPadding,
            };
        },
    },

    compatConfig: {
        INSTANCE_ATTRS_CLASS_STYLE: false,
    },

    methods: {
        cardClasses() {
            return {
                'sw-card--tabs': !!this.$slots.tabs || !!this.$scopedSlots.tabs,
                'sw-card--grid': !!this.$slots.grid || !!this.$scopedSlots.grid,
                'sw-card--hero': !!this.hero,
                'sw-card--large': this.large,
                'has--header': !!this.showHeader,
                'has--title': !!this.title || !!this.$slots.title || !!this.$scopedSlots.title,
                'has--subtitle': !!this.subtitle || !!this.$slots.subtitle || !!this.$scopedSlots.subtitle,
                'has--toolbar': !!this.$slots.toolbar || !!this.$scopedSlots.toolbar,
                'has--tabs': !!this.$slots.tabs || !!this.$scopedSlots.tabs,
            };
        },
    },
});
