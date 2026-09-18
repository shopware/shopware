import { Text, type VNode } from 'vue';
import type { RouteLocationRaw, Router } from 'vue-router';
import type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
import template from './sw-tabs.html.twig';

type SwTabsItemProps = {
    name?: string;
    route?: RouteLocationRaw;
    title?: string;
};

type VNodeTypeWithName = {
    name?: string;
};

type VNodeChildrenWithDefaultSlot = {
    default?: () => VNode[];
};

function isTabItemVNode(vnode: VNode): boolean {
    return (vnode.type as VNodeTypeWithName | undefined)?.name === 'sw-tabs-item';
}

function isFragmentVNode(vnode: VNode): boolean {
    // A `v-for` of `sw-tabs-item` is wrapped in a fragment vnode.
    return typeof vnode.type === 'symbol' && vnode.type.toString() === 'Symbol(v-fgt)';
}

/**
 * Returns the text of a `sw-tabs-item`'s default slot, or `undefined` when it has none.
 * Used as the label fallback for items that provide their label as slot text.
 */
function getTabItemSlotText(vnode: VNode): string | undefined {
    const slotChild = (vnode.children as VNodeChildrenWithDefaultSlot | null)?.default?.()?.[0];

    return slotChild?.type === Text ? (slotChild.children as string) : undefined;
}

/**
 * Maps a legacy `sw-tabs-item` vnode to the `mt-tabs` item format.
 *
 * The label is resolved from the `title` prop, then the default slot text, so an item whose label is
 * only provided as slot text (`<sw-tabs-item :name="id">{{ label }}</sw-tabs-item>`) still renders it.
 */
function resolveTabItem(vnode: VNode, router: Router): TabItem {
    const props = (vnode.props ?? {}) as SwTabsItemProps;

    const label = props.title ?? getTabItemSlotText(vnode) ?? '';
    const name = props.name ?? props.title ?? label;

    const tabItem: TabItem = {
        label,
        name,
    };

    if (props.route) {
        tabItem.onClick = () => {
            if (props.route) {
                void router.push(props.route);
            }
        };
    }

    return tabItem;
}

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @deprecated tag:v6.9.0 - Will be removed. Use `mt-tabs` instead.
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        /**
         * Enables the temporary Meteor compatibility path.
         */
        useMeteorComponent: {
            type: Boolean,
            required: false,
            default: false,
        },

        /**
         * Only used for new mt-tabs component
         */
        items: {
            type: Array as PropType<TabItem[]>,
            required: false,
        },
    },

    computed: {
        shouldUseMeteorComponent() {
            if (this.useMeteorComponent) {
                return true;
            }

            if (Shopware.Feature.isActive('V6_8_0_0')) {
                Shopware.Utils.debug.warn(
                    'sw-tabs',
                    'The "sw-tabs" wrapper is deprecated and will be removed in v6.9.0.0. Please use "mt-tabs" instead.',
                );
            }

            return false;
        },

        itemsBackwardCompatible(): TabItem[] {
            if (this.items) {
                return this.items;
            }

            const defaultSlotContent = this.$slots.default?.({});

            if (!defaultSlotContent) {
                return [];
            }

            // Convert the slotted `sw-tabs-item` vnodes into `mt-tabs` items. A `v-for` of items is
            // wrapped in a fragment vnode, so its children are unwrapped and mapped individually.
            return defaultSlotContent.flatMap((item) => {
                // v-for
                if (isFragmentVNode(item)) {
                    const children = Array.isArray(item.children) ? (item.children as VNode[]) : [];

                    return children.filter(isTabItemVNode).map((child) => resolveTabItem(child, this.$router));
                }

                // normal cases
                if (isTabItemVNode(item)) {
                    return [resolveTabItem(item, this.$router)];
                }

                return [];
            });
        },
    },

    data(): {
        activeItem: unknown;
    } {
        return {
            activeItem: null,
        };
    },

    mounted() {
        // Set first item as active
        if (this.itemsBackwardCompatible.length > 0) {
            this.activeItem = this.itemsBackwardCompatible[0].name;
        }
    },

    methods: {
        getSlots() {
            return this.$slots;
        },

        mountedComponent() {
            // Fallback for $refs access in some modules
            const tabComponent = this.$refs.tabComponent as { mountedComponent?: () => void } | undefined;
            tabComponent?.mountedComponent?.();
        },

        setActiveItem(item: unknown) {
            // Fallback for $refs access in some modules
            const tabComponent = this.$refs.tabComponent as { setActiveItem?: (item: unknown) => void } | undefined;
            tabComponent?.setActiveItem?.(item);
        },

        onNewItemActive(item: unknown) {
            this.$emit('new-item-active', item);
            this.activeItem = item;
        },
    },
});
