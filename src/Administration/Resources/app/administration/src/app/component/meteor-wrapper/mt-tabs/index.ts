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

        useRoutesForExtensions: {
            type: Boolean,
            required: false,
            default: true,
        },

        small: {
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
            return this.useRoutesForExtensions && !this.$slots.content;
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
                ...this.tabExtensions.map((extension) => {
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
