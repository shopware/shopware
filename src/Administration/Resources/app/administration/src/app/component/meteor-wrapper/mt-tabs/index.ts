import { MtTabs } from '@shopware-ag/meteor-component-library';
import type { PropType } from 'vue';
import type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/components/navigation/mt-tabs/mt-tabs';
import template from './mt-tabs.html.twig';
import type { TabItemEntry } from '../../../state/tabs.store';

// Use the compatConfig from the Shopware object and disable all compatibilities
// eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
MtTabs.compatConfig = Object.fromEntries(
    Object.keys(Shopware.compatConfig).map((key) => [
        key,
        false,
    ]),
);

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description Wrapper component for mt-tabs. Adds the component sections
 *  to the slots. Need to be matched with the original mt-tabs component.
 */
Shopware.Component.register('mt-tabs', {
    template,

    compatConfig: Shopware.compatConfig,

    components: {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        'mt-tabs-original': MtTabs,
    },

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
    },

    computed: {
        tabExtensions(): TabItemEntry[] {
            return Shopware.State.get('tabs').tabItems[this.positionIdentifier] ?? [];
        },

        mergedItems(): TabItem[] {
            const mergedItems: TabItem[] = [
                ...this.items,
                ...this.tabExtensions.map((extension) => ({
                    label: this.$t(extension.label) ?? '',
                    name: extension.componentSectionId,
                    onClick: () => {
                        // Push route to extension.componentSectionId path
                        void this.$router.push({
                            path: extension.componentSectionId,
                        });
                    },
                })),
            ];

            return mergedItems;
        },

        listeners() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_LISTENERS')) {
                return this.$listeners;
            }

            return {};
        },
    },
});
