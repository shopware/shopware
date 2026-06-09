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

            return this.getTabItemsFromSlotContent(slotContent);
        },

        getTabItemsFromSlotContent(slotContent) {
            return slotContent.reduce((items, item) => {
                if (this.isFragment(item)) {
                    const children = Array.isArray(item.children) ? item.children : [];

                    return [
                        ...items,
                        ...this.getTabItemsFromSlotContent(children),
                    ];
                }

                if (!this.isTabItem(item)) {
                    return items;
                }

                return [
                    ...items,
                    this.createTabItem(item),
                ];
            }, []);
        },

        createTabItem(item) {
            const props = item.props ?? {};
            const label = props.title ?? this.getTabItemDefaultSlotText(item) ?? props.name ?? '';
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

                    this.triggerTabItemClick(props.onClick);
                };
            }

            return tabItem;
        },

        triggerTabItemClick(clickHandler) {
            if (Array.isArray(clickHandler)) {
                clickHandler.forEach((handler) => {
                    handler();
                });

                return;
            }

            if (typeof clickHandler === 'function') {
                clickHandler();
            }
        },

        getTabItemDefaultSlotText(item) {
            const defaultSlotContent = item.children?.default?.();

            if (!defaultSlotContent) {
                return undefined;
            }

            const slotText = defaultSlotContent
                .map((slotItem) => this.getTextFromSlotItem(slotItem))
                .join('')
                .trim();

            return slotText || undefined;
        },

        getTextFromSlotItem(slotItem) {
            if (typeof slotItem.children === 'string') {
                return slotItem.children;
            }

            if (Array.isArray(slotItem.children)) {
                return slotItem.children.map((child) => this.getTextFromSlotItem(child)).join('');
            }

            return '';
        },

        isTabItem(item) {
            return item.type?.name === 'sw-tabs-item';
        },

        isFragment(item) {
            return item.type?.toString() === 'Symbol(v-fgt)';
        },
    },
};
