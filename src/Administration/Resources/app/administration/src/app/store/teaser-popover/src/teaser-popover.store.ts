/**
 * @sw-package innovation
 */
import { defineStoreOptions, getStore, type StoreOf } from '../dependencies';
/**
 * @public
 */
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

/**
 * @public
 */
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
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const teaserPopoverStoreId = 'teaserPopover';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const teaserPopoverStoreOptions = defineStoreOptions({
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
 * @public
 */
export type TeaserPopoverStore = StoreOf<typeof teaserPopoverStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useTeaserPopoverStore = (): TeaserPopoverStore => getStore(teaserPopoverStoreId);
