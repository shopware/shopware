import { type VNode } from 'vue';
import type { RouteLocationNamedRaw, RouteLocationRaw } from 'vue-router';
import type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
import type { ModuleManifest } from 'src/core/factory/module.factory';
import { getTabItemsFromSlotContent, getTextFromSlotItem, triggerTabItemClick } from '../tab-slot-parser';
import template from './sw-meteor-page.html.twig';
import './sw-meteor-page.scss';

type ComponentData = {
    module: ModuleManifest | null;
    parentRoute: string | null;
};

type SwTabsItemProps = {
    disabled?: boolean;
    hasError?: boolean;
    hasWarning?: boolean;
    name?: string;
    onClick?: (() => void) | Array<() => void>;
    route?: RouteLocationRaw;
    title?: string;
};

type VNodeTypeWithName = {
    name?: string;
};

type VNodeChildrenWithDefaultSlot = {
    default?: () => VNode[];
};

/**
 * @sw-package framework
 *
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'feature',
    ],

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

        hideSmartBar: {
            type: Boolean,
            required: false,
            default: false,
        },

        fromLink: {
            type: Object as PropType<RouteLocationNamedRaw | null>,
            required: false,
            default: null,
        },
    },

    data(): ComponentData {
        return {
            module: null,
            parentRoute: null,
        };
    },

    computed: {
        pageClasses(): object {
            return {
                'sw-meteor-page--full-width': this.fullWidth,
                'sw-meteor-page--hide-smart-bar': this.hideSmartBar,
            };
        },

        hasIcon(): boolean {
            return typeof this.module?.icon === 'string';
        },

        hasIconOrIconSlot(): boolean {
            return this.hasIcon || typeof this.$slots['smart-bar-icon'] !== 'undefined';
        },

        hasTabs(): boolean {
            return typeof this.$slots['page-tabs'] !== 'undefined';
        },

        tabItems(): TabItem[] {
            return this.getTabItemsFromSlot();
        },

        defaultTab(): string {
            const routeName = typeof this.$route?.name === 'string' ? this.$route.name : undefined;

            if (routeName && this.tabItems.some((tab) => tab.name === routeName)) {
                return routeName;
            }

            return this.tabItems[0]?.name ?? '';
        },
    },

    beforeUnmount(): void {
        void Shopware.Store.get('error').resetApiErrors();
    },

    mounted(): void {
        this.mountedComponent();
    },

    methods: {
        mountedComponent(): void {
            this.initPage();
        },

        emitNewTab(tabItem: string): void {
            this.$emit('new-item-active', tabItem);
        },

        getTabItemsFromSlot(): TabItem[] {
            const slotContent = this.$slots['page-tabs']?.();

            if (!slotContent) {
                return [];
            }

            return getTabItemsFromSlotContent(slotContent, {
                isTabItem: (item) => this.isTabItem(item),
                createTabItem: (item) => this.createTabItem(item),
            });
        },

        createTabItem(item: VNode): TabItem {
            const props = (item.props ?? {}) as SwTabsItemProps;
            const routeName = this.getRouteName(props.route);
            const slotText = this.getTabItemDefaultSlotText(item);
            const label = slotText ?? props.title ?? props.name ?? routeName ?? '';
            const tabItem: TabItem = {
                label,
                name: props.name ?? props.title ?? routeName ?? label,
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
                        void this.$router.push(props.route);
                    }

                    triggerTabItemClick(props.onClick);
                };
            }

            return tabItem;
        },

        getTabItemDefaultSlotText(item: VNode): string | undefined {
            const children = item.children as VNodeChildrenWithDefaultSlot | undefined;
            const defaultSlotContent = children?.default?.();

            if (!defaultSlotContent) {
                return undefined;
            }

            return defaultSlotContent
                .map((slotItem) => getTextFromSlotItem(slotItem))
                .join('')
                .trim();
        },

        getRouteName(route: RouteLocationRaw | undefined): string | undefined {
            if (typeof route !== 'object' || route === null || !('name' in route)) {
                return undefined;
            }

            const namedRoute = route as RouteLocationNamedRaw;

            return typeof namedRoute.name === 'string' ? namedRoute.name : undefined;
        },

        isTabItem(item: VNode): boolean {
            const props = item.props as SwTabsItemProps | null;
            const children = item.children as VNodeChildrenWithDefaultSlot | undefined;

            return (
                (item.type as VNodeTypeWithName | undefined)?.name === 'sw-tabs-item' ||
                (typeof children?.default === 'function' &&
                    props !== null &&
                    (props.name !== undefined || props.route !== undefined || props.title !== undefined))
            );
        },

        initPage(): void {
            if (typeof this.$route?.meta?.$module !== 'undefined') {
                this.module = this.$route.meta.$module as ModuleManifest | null;
            }

            if (typeof this.$route?.meta?.parentPath === 'string') {
                this.parentRoute = this.$route.meta.parentPath;
            }
        },
    },
});
