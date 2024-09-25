/**
 * @package customer-order
 * @private
 * @description Apply for upselling service only, no public usage
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type TeaserPopoverConfig = {
    positionId: string,
    src: string,
    component: string,
    props: {
        label?: string,
        locationId: string,
        locationTriggerId?: string,
        variant?: string,
        icon?: string,
    };
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type TeaserSalesChannelConfig = {
    positionId: string,
    salesChannel: {
        title: string,
        description: string,
        iconName: string,
    };
    popoverComponent: {
        src: string,
        component: string,
        props: {
            label: string,
            locationId: string,
            variant: string,
        }
    }
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type TeaserPopoverState = {
    state: {
        identifier: {
            [positionId: string]: TeaserPopoverConfig,
        },
        salesChannels: TeaserSalesChannelConfig[],
    },
    actions: {
        addPopoverComponent: (popoverComponent: TeaserPopoverConfig) => void,
        addSalesChannel: (popoverComponent: TeaserSalesChannelConfig) => void,
    },
    getters: unknown,
};

const TeaserPopoverState = Shopware.Store.wrapStoreDefinition({
    id: 'teaserPopoverState',

    state: (): TeaserPopoverState['state'] => ({
        identifier: {},
        salesChannels: [],
    }),

    actions: {
        addPopoverComponent(popoverComponent: TeaserPopoverConfig): void {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            this.identifier[popoverComponent.positionId] = {
                ...popoverComponent,
            };
        },

        addSalesChannel(popoverComponent: TeaserSalesChannelConfig): void {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            this.salesChannels.push(popoverComponent);
        },
    },
});

/**
 * @private
 */
export default TeaserPopoverState;
