import template from './sw-extension-teaser-sales-channel.html.twig';
import './sw-extension-teaser-sales-channel.scss';

interface TeaserSalesChannelConfig {
    positionId: string;
    salesChannel: {
        title: string;
        description: string;
        iconName: string;
    };
    popoverComponent: {
        component: string;
        src: string;
        props: {
            label: string;
            locationId: string;
            variant: string;
        }
    }
}

/**
 * @package customer-order
 *
 * @private
 * @description A teaser sales channel for upselling service only, no public usage
  * @example-type dynamic
  * @component-example
  * <sw-extension-teaser-sales-channel />
 */
Shopware.Component.register('sw-extension-teaser-sales-channel', {
    template,

    compatConfig: Shopware.compatConfig,

    computed: {
        teaserSalesChannels(): TeaserSalesChannelConfig[] {
            return Shopware.Store.get('teaserPopoverState').salesChannels || [];
        },
    },
});
