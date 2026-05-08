import MtTabs from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
import type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
import template from './mt-tabs.html.twig';
import type { TabItemEntry } from '../../../store/tabs.store';

type ComponentData = {
    recentLocalExtensionTab: string | null;
};

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

    emits: ['new-item-active'],

    props: {
        positionIdentifier: {
            type: String,
            required: true,
            default: null,
        },

        items: {
            type: Array as PropType<TabItem[]>,
            required: true,
        },

        routeExtensionTabs: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data(): ComponentData {
        return {
            recentLocalExtensionTab: null,
        };
    },

    computed: {
        tabExtensions(): TabItemEntry[] {
            return Shopware.Store.get('tabs').tabItems[this.positionIdentifier] ?? [];
        },

        mergedItems(): TabItem[] {
            const mergedItems: TabItem[] = [
                ...this.items,
                ...this.tabExtensions.map((extension) => ({
                    label: this.$t(extension.label) ?? '',
                    name: extension.componentSectionId,
                    onClick: () => {
                        if (!this.routeExtensionTabs) {
                            if (this.recentLocalExtensionTab === extension.componentSectionId) {
                                this.recentLocalExtensionTab = null;
                                return;
                            }

                            this.$emit('new-item-active', {
                                label: this.$t(extension.label) ?? '',
                                name: extension.componentSectionId,
                            });
                            return;
                        }

                        // Push route to extension.componentSectionId path
                        void this.$router.push({
                            path: extension.componentSectionId,
                        });
                    },
                })),
            ];

            return mergedItems;
        },
    },

    methods: {
        getActiveItemName(activeItem: TabItem | string) {
            return typeof activeItem === 'string' ? activeItem : activeItem.name;
        },

        onNewItemActive(activeItem: TabItem | string) {
            const activeItemName = this.getActiveItemName(activeItem);

            if (
                !this.routeExtensionTabs &&
                this.tabExtensions.some((extension) => extension.componentSectionId === activeItemName)
            ) {
                this.recentLocalExtensionTab = activeItemName;
            }

            this.$emit('new-item-active', activeItem);
        },
    },
});
