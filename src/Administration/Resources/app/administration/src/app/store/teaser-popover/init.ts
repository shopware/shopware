/**
 * @sw-package innovation
 */
import { useTeaserPopoverStore } from './src/teaser-popover.store';
import type { TeaserSalesChannelConfig, TeaserPopoverConfig } from './src/teaser-popover.store';

/**
 * @private
 * @description Apply for upselling service only, no public usage
 */
export default function initializeTeaserPopovers(): void {
    const store = useTeaserPopoverStore();

    Shopware.ExtensionAPI.handle(
        // @ts-expect-error - There are no types for this as it is private API
        '__upsellingTeaserPopover',
        (configuration: TeaserSalesChannelConfig | TeaserPopoverConfig) => {
            if (configuration.positionId === 'sales-channel') {
                store.addSalesChannel(configuration as TeaserSalesChannelConfig);
                return;
            }

            store.addPopoverComponent(configuration as TeaserPopoverConfig);
        },
    );
}
