/**
 * @sw-package innovation
 * @private
 * @description Apply for upselling service only, no public usage
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type TeaserPopoverConfig = {
    positionId: string;
    src: string;
    component: string;
    props: {
        label?: string;
        locationId: string;
        locationTriggerId?: string;
        variant?: string;
        icon?: string;
    };
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type TeaserSalesChannelConfig = {
    positionId: string;
    salesChannel: {
        title: string;
        description: string;
        iconName: string;
    };
    popoverComponent: {
        src: string;
        component: string;
        props: {
            label: string;
            locationId: string;
            variant: string;
        };
    };
};

/**
 * @private
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const teaserPopoverStoreId = 'teaserPopover';

const teaserPopoverStore = Shopware.Store.register({
    id: teaserPopoverStoreId,

    state: () => ({
        identifier: {} as Record<string, TeaserPopoverConfig>,
        salesChannels: [] as TeaserSalesChannelConfig[],
    }),

    actions: {
        addPopoverComponent(popoverComponent: TeaserPopoverConfig): void {
            this.identifier[popoverComponent.positionId] = {
                ...popoverComponent,
            };
        },

        addSalesChannel(popoverComponent: TeaserSalesChannelConfig): void {
            this.salesChannels.push(popoverComponent);
        },
    },
});

/**
 * @private
 */
export type TeaserPopoverStore = ReturnType<typeof teaserPopoverStore>;

/**
 * @private
 */
export default teaserPopoverStore;
