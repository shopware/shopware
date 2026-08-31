import { getTabItemsFromSlotContent, getTextFromSlotItem, triggerTabItemClick } from '../tab-slot-parser';
import template from './sw-meteor-card.html.twig';
import './sw-meteor-card.scss';

/**
 * @sw-package framework
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
export default {
    template,

    inject: [
        'feature',
    ],

    props: {
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
            return !!this.$slots.tabs;
        },

        hasToolbar() {
            return !!this.$slots.toolbar;
        },

        hasContent() {
            return !!this.$slots.default || !!this.$slots.grid;
        },

        hasDefaultSlot() {
            return !!this.$slots.default;
        },

        tabItems() {
            return this.getTabItemsFromSlot();
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

        getTabItemsFromSlot() {
            const slotContent = this.$slots.tabs?.({
                activeTab: this.activeTab,
            });

            if (!slotContent) {
                return [];
            }

            return getTabItemsFromSlotContent(slotContent, {
                isTabItem: (item) => this.isTabItem(item),
                createTabItem: (item) => this.createTabItem(item),
            });
        },

        createTabItem(item) {
            const props = item.props ?? {};
            const slotText = this.getTabItemDefaultSlotText(item);
            const label = slotText ?? props.title ?? props.name ?? '';
            const tabItem = {
                label,
                name: props.name ?? props.title ?? label,
            };

            if (props.hasError !== undefined) {
                tabItem.hasError = props.hasError;
            }

            if (props.disabled !== undefined) {
                tabItem.disabled = props.disabled;
            }

            if (props.hasWarning) {
                tabItem.badge = 'warning';
            }

            if (props.route || props.onClick) {
                tabItem.onClick = () => {
                    if (props.route) {
                        this.$router.push(props.route);
                    }

                    triggerTabItemClick(props.onClick);
                };
            }

            return tabItem;
        },

        getTabItemDefaultSlotText(item) {
            const defaultSlotContent = item.children?.default?.();

            if (!defaultSlotContent) {
                return undefined;
            }

            const slotText = defaultSlotContent
                .map((slotItem) => getTextFromSlotItem(slotItem))
                .join('')
                .trim();

            return slotText || undefined;
        },

        isTabItem(item) {
            const props = item.props ?? {};

            return (
                item.type?.name === 'sw-tabs-item' ||
                (typeof item.children?.default === 'function' &&
                    (props.name !== undefined ||
                        props.route !== undefined ||
                        props.title !== undefined ||
                        props.activeTab !== undefined))
            );
        },
    },
};
