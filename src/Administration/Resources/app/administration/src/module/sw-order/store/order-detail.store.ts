/**
 * @sw-package checkout
 */
import type { ContextState } from '../../../app/composables/use-context';

const { Criteria } = Shopware.Data;

/**
 * Kept outside of the store state on purpose: it only deduplicates concurrent requests and must not be reactive.
 */
let pendingCustomerRequest: {
    customerId: EntityKey<'customer'>;
    request: Promise<Entity<'customer'> | null>;
} | null = null;

interface OrderAddressId {
    orderAddressId: EntityKey<'order_address'>;
    customerAddressId: EntityKey<'customer_address'>;
    type: string;
    edited: boolean;
}

const swOrderDetailStore = Shopware.Store.register({
    id: 'swOrderDetail',

    state() {
        return {
            order: null as Entity<'order'> | null,
            customer: null as Entity<'customer'> | null,
            loading: {
                order: false, // live version id
                recalculation: false, // custom version id
                states: false,
            },
            editing: false,
            savedSuccessful: false,
            versionContext: null as ContextState['api'] | null,
            orderAddressIds: [] as OrderAddressId[],
        };
    },

    getters: {
        isLoading: (state) => {
            return Object.values(state.loading).some((loadState) => loadState);
        },

        isEditing: (state) => {
            return state.editing;
        },
    },

    actions: {
        setLoading(value: [keyof typeof this.loading, boolean]) {
            const name = value[0];
            const data = value[1];

            // check for use from .js files
            if (typeof data !== 'boolean') {
                return;
            }
            this.loading[name] = data;
        },

        /**
         * Loading the customer once into the store keeps order detail components (i.e. order address selection
         * components) in sync, so creations and edits in one component are immediately available in all others.
         */
        loadCustomer(customerId: EntityKey<'customer'>, reload = false): Promise<Entity<'customer'> | null> {
            if (!reload && this.customer?.id === customerId) {
                return Promise.resolve(this.customer);
            }

            if (!reload && pendingCustomerRequest?.customerId === customerId) {
                return pendingCustomerRequest.request;
            }

            const criteria = new Criteria(1, 25);
            criteria.addAssociation('addresses.country');

            const request = Shopware.Service('repositoryFactory')
                .create('customer')
                .get(customerId, Shopware.Context.api, criteria)
                .then((customer) => {
                    this.customer = customer;

                    return customer;
                })
                .finally(() => {
                    if (pendingCustomerRequest?.request === request) {
                        pendingCustomerRequest = null;
                    }
                });

            pendingCustomerRequest = { customerId, request };

            return request;
        },

        resetCustomer() {
            this.customer = null;
            pendingCustomerRequest = null;
        },

        setOrderAddressIds(value?: OrderAddressId | null) {
            if (!value) {
                this.orderAddressIds = [];
                return;
            }

            const { orderAddressId, customerAddressId, type, edited } = value;

            // Handle deletion scenario where orderAddressId matches customerAddressId
            if (String(orderAddressId) === String(customerAddressId) && !edited) {
                this.orderAddressIds = this.orderAddressIds.filter(
                    (ids) => !(ids.orderAddressId === orderAddressId && ids.type === type),
                );

                return;
            }

            // Find index of the existing item
            const index = this.orderAddressIds.findIndex(
                (ids) => ids.orderAddressId === orderAddressId && ids.type === type,
            );

            // If found, update the existing item
            if (index !== -1) {
                this.orderAddressIds[index].customerAddressId = customerAddressId;

                return;
            }

            // Add a new item if no existing item was found
            this.orderAddressIds.push(value);
        },
    },
});

/**
 * @private
 */
export default swOrderDetailStore;

/**
 * @private
 */
export type SwOrderDetailStore = ReturnType<typeof swOrderDetailStore>;
