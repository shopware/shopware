/**
 * @package checkout
 * @deprecated tag:v6.7.0 - Will be replaced with Pinia store
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    namespaced: true,

    state() {
        return {
            order: null,
            loading: {
                order: false,
                states: false,
            },
            editing: false,
            savedSuccessful: false,
            versionContext: null,
            orderAddressIds: [],
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

    mutations: {
        setOrder(state, newOrder) {
            state.order = newOrder;
        },

        setLoading(state, value) {
            const name = value[0];
            const data = value[1];

            if (typeof data !== 'boolean') {
                return;
            }

            if (state.loading[name] !== undefined) {
                state.loading[name] = data;
            }
        },

        setEditing(state, value) {
            state.editing = value;
        },

        setSavedSuccessful(state, value) {
            state.savedSuccessful = value;
        },

        setVersionContext(state, versionContext) {
            state.versionContext = versionContext;
        },

        setOrderAddressIds(state, value) {
            if (!value) {
                state.orderAddressIds = [];
                return;
            }

            const { orderAddressId, customerAddressId, type, edited } = value;

            // Handle deletion scenario where orderAddressId matches customerAddressId
            if (orderAddressId === customerAddressId && !edited) {
                state.orderAddressIds = state.orderAddressIds.filter(
                    ids => !(ids.orderAddressId === orderAddressId && ids.type === type),
                );

                return;
            }

            // Find index of the existing item
            const index = state.orderAddressIds.findIndex(
                ids => ids.orderAddressId === orderAddressId && ids.type === type,
            );

            // If found, update the existing item
            if (index !== -1) {
                state.orderAddressIds[index].customerAddressId = customerAddressId;

                return;
            }

            // Add a new item if no existing item was found
            state.orderAddressIds.push(value);
        },
    },
};
