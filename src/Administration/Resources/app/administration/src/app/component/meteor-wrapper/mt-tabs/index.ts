import MtTabs from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
import type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
import template from './mt-tabs.html.twig';
import type { TabItemEntry } from '../../../store/tabs.store';

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

    inheritAttrs: false,

    emits: [
        'new-item-active',
        'extension-item-active',
    ],

    components: {
        'mt-tabs-original': MtTabs,
    },

    props: {
        positionIdentifier: {
            type: String,
            default: null,
        },

        items: {
            type: Array as PropType<TabItem[]>,
            required: true,
        },

        routeTabs: {
            type: Boolean,
            required: false,
            default: false,
        },

        renderExtensionContent: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data(): { activeItemName: string } {
        return {
            activeItemName: typeof this.$attrs.defaultItem === 'string' ? this.$attrs.defaultItem : '',
        };
    },

    computed: {
        forwardedAttrs(): Record<string, unknown> {
            const attrs = { ...this.$attrs };
            delete attrs.onNewItemActive;
            delete attrs.defaultItem;

            return attrs;
        },

        effectiveActiveItemName(): string {
            return this.getActiveRouteExtensionItemName() ?? this.activeItemName;
        },

        tabExtensions(): TabItemEntry[] {
            if (!this.positionIdentifier) {
                return [];
            }

            return Shopware.Store.get('tabs').tabItems[this.positionIdentifier] ?? [];
        },

        mergedItems(): TabItem[] {
            const mergedItems: TabItem[] = [
                ...this.items,
                ...this.tabExtensions.map((extension) => ({
                    label: this.$t(extension.label) ?? '',
                    name: extension.componentSectionId,
                    ...(this.routeTabs
                        ? {
                              onClick: () => {
                                  void this.$router.push(this.getExtensionRoute(extension.componentSectionId));
                              },
                          }
                        : {}),
                })),
            ];

            return mergedItems;
        },
    },

    watch: {
        '$attrs.defaultItem'(defaultItem: string) {
            this.activeItemName = defaultItem;
        },
    },

    methods: {
        onNewItemActive(itemName: string) {
            this.activeItemName = itemName;

            if (this.isExtensionItem(itemName)) {
                this.$emit('extension-item-active', itemName);
                return;
            }

            this.$emit('new-item-active', itemName);
        },

        isExtensionItem(itemName: string): boolean {
            return this.tabExtensions.some((extension) => extension.componentSectionId === itemName);
        },

        getExtensionRoute(componentSectionId: string) {
            const query = this.$route?.query ?? {};

            if (Object.keys(query).length === 0) {
                return { path: componentSectionId };
            }

            return {
                path: componentSectionId,
                query,
            };
        },

        getActiveRouteExtensionItemName(): string | null {
            if (!this.routeTabs) {
                return null;
            }

            const activePath = String(this.$route?.fullPath ?? this.$route?.path ?? '').split(/[?#]/)[0];

            return (
                this.tabExtensions.find((extension) => activePath.endsWith(extension.componentSectionId))
                    ?.componentSectionId ?? null
            );
        },
    },
});
