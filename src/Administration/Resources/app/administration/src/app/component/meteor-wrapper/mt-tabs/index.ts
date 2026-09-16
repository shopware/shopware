import MtTabs from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
import type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
import template from './mt-tabs.html.twig';
import type { TabItemEntry } from '../../../store/tabs.store';
import './mt-tabs.scss';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for mt-tabs. Adds the component sections
 *  to the slots. Need to be matched with the original mt-tabs component.
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    components: {
        'mt-tabs-original': MtTabs,
    },

    emits: [
        'new-item-active',
    ],

    props: {
        positionIdentifier: {
            type: String,
            required: true,
            default: null,
        },

        defaultItem: {
            type: String,
            required: false,
            default: '',
        },

        /**
         * Controls whether extension-provided tabs navigate via the router or render their
         * component section inline. When omitted, the wrapper infers the mode from the
         * surface's own items: route-backed surfaces give their items an `onClick` (route
         * navigation), local-state surfaces do not. An explicit value always wins over the
         * inference. Note: a local surface whose items carry an `onClick` for non-routing
         * reasons (e.g. filtering) must set this to `false` explicitly.
         */
        useRoutesForExtensions: {
            type: Boolean,
            required: false,
            default: undefined,
        },

        small: {
            type: Boolean,
            required: false,
            default: false,
        },

        vertical: {
            type: Boolean,
            required: false,
            default: false,
        },

        items: {
            type: Array as PropType<TabItem[]>,
            required: true,
        },
    },

    data(): {
        activeItem: string;
    } {
        return {
            activeItem: this.defaultItem,
        };
    },

    computed: {
        meteorAttributes(): Record<string, unknown> {
            const attributes = { ...this.$attrs };
            delete attributes['position-identifier'];

            return attributes;
        },

        tabExtensions(): TabItemEntry[] {
            return Shopware.Store.get('tabs').tabItems[this.positionIdentifier] ?? [];
        },

        activeItemName(): string {
            if (!this.extensionTabsUseRoutes) {
                return this.defaultItem;
            }

            const defaultItemExists = this.mergedItems.some((item) => {
                return item.name === this.defaultItem;
            });

            if (defaultItemExists) {
                return this.defaultItem;
            }

            return (
                this.tabExtensions.find((extension) => {
                    return this.defaultItem.endsWith(`.${extension.componentSectionId}`);
                })?.componentSectionId ?? this.defaultItem
            );
        },

        extensionTabsUseRoutes(): boolean {
            if (this.$slots.content) {
                return false;
            }

            if (typeof this.useRoutesForExtensions === 'boolean') {
                return this.useRoutesForExtensions;
            }

            return this.items.some((item) => typeof item.onClick === 'function');
        },

        activeTabExtension(): TabItemEntry | undefined {
            return this.tabExtensions.find((extension) => {
                return extension.componentSectionId === this.activeItem;
            });
        },

        hasCustomContent(): boolean {
            return Boolean(this.$slots.content || (this.activeTabExtension && !this.extensionTabsUseRoutes));
        },

        mergedItems(): TabItem[] {
            const mergedItems: TabItem[] = [
                ...this.items,
                ...this.tabExtensions
                    .filter((extension) => extension.visible !== false)
                    .map((extension) => {
                        const tabItem: TabItem = {
                            label: this.$t(extension.label) ?? '',
                            name: extension.componentSectionId,
                        };

                        if (this.extensionTabsUseRoutes) {
                            tabItem.onClick = () => {
                                // Push route to extension.componentSectionId path
                                void this.$router.push({
                                    path: extension.componentSectionId,
                                });
                            };
                        }

                        return tabItem;
                    }),
            ];

            return mergedItems;
        },
    },

    watch: {
        defaultItem() {
            this.activeItem = this.defaultItem;
        },
    },

    methods: {
        onNewItemActive(itemName: string): void {
            this.activeItem = itemName;
            this.$emit('new-item-active', itemName);
        },
    },
});
