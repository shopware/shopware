/**
 * @package customer-order
 *
 * @private
 * @description Apply for upselling service only, no public usage
 */

import teaserPopoverState from 'src/app/store/teaser-popover.store';
import type { TeaserSalesChannelConfig, TeaserPopoverConfig } from 'src/app/store/teaser-popover.store';
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeTeaserPopovers(): void {
    Shopware.Store.register(teaserPopoverState);
    const store = Shopware.Store.get('teaserPopoverState');

    Shopware.ExtensionAPI.handle(
        // @ts-expect-error - There are no types for this as it is private API
        '__upsellingTeaserPopover',
        (configuration: TeaserSalesChannelConfig | TeaserPopoverConfig) => {
            if (configuration.positionId === 'sales-channel') {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
                store.addSalesChannel(configuration as TeaserSalesChannelConfig);
                return;
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            store.addPopoverComponent(configuration as TeaserPopoverConfig);
        },
    );
}
