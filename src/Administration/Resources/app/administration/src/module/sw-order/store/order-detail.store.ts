/**
 * @sw-package checkout
 */
import type { ContextState } from '../../../app/composables/use-context';

interface OrderAddressId {
    orderAddressId: string;
    customerAddressId: string;
    type: string;
    edited: boolean;
}

const swOrderDetailStore = Shopware.Store.register({
    id: 'swOrderDetail',

    state() {
        return {
            order: null as EntitySchema.order | null,
            loading: {
                order: false,
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

        setOrderAddressIds(value: OrderAddressId) {
            if (!value) {
                this.orderAddressIds = [];
                return;
            }

            const { orderAddressId, customerAddressId, type, edited } = value;

            // Handle deletion scenario where orderAddressId matches customerAddressId
            if (orderAddressId === customerAddressId && !edited) {
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
