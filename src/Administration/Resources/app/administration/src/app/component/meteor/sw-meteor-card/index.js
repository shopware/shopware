/* eslint-disable indent */
import template from './sw-meteor-card.html.twig';
import './sw-meteor-card.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @description A card is a flexible and extensible content container.
 * @status ready
 * @example-type static
 * @example-description This example illustrates the usage of tabs with this component.
 * @component-example
 *
 * <sw-meteor-card defaultTab="tab1">
 *     <template #tabs="{ activeTab }">
 *         <sw-tabs-item name="tab1" :activeTab="activeTab">Tab 1</sw-tabs-item>
 *         <sw-tabs-item name="tab2" :activeTab="activeTab">Tab 2</sw-tabs-item>
 *     </template>
 *
 *     <template #default="{ activeTab }">
 *         <p v-if="activeTab === 'tab1'">Tab 1</p>
 *         <p v-if="activeTab === 'tab2'">Tab 2</p>
 *     </template>
 * </sw-meteor-card>
 */
Component.register('sw-meteor-card', {
    template,

    props: {
        // eslint-disable-next-line vue/require-default-prop
        title: {
            type: String,
            required: false,
            default: null,
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

        defaultTab: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            activeTab: null,
        };
    },

    computed: {
        hasTabs() {
            return !!this.$slots.tabs || !!this.$scopedSlots.tabs;
        },

        hasToolbar() {
            return !!this.$slots.toolbar || !!this.$scopedSlots.toolbar;
        },

        hasHeader() {
            return this.hasToolbar || this.hasTabs || !!this.title || !!this.$slots.action;
        },

        isToolbarLastHeaderElement() {
            return this.hasToolbar && !this.hasTabs;
        },

        cardClasses() {
            return {
                'sw-meteor-card--tabs': this.hasTabs,
                'sw-meteor-card--toolbar': this.hasToolbar,
                'sw-meteor-card--hero': !!this.hero,
                'sw-meteor-card--large': this.large,
                'has--header': this.hasHeader && !this.isToolbarLastHeaderElement,
            };
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.setActiveTab(this.defaultTab);
        },

        setActiveTab(name) {
            this.activeTab = name;
        },
    },
});
