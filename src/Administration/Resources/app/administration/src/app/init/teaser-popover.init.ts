/**
 * @sw-package innovation
 *
 * @private
 * @description Apply for upselling service only, no public usage
 */

import 'src/app/store/teaser-popover.store';
import type { TeaserSalesChannelConfig, TeaserPopoverConfig } from 'src/app/store/teaser-popover.store';
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeTeaserPopovers(): void {
    const store = Shopware.Store.get('teaserPopover');

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
