/**
 * @package customer-order
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

            const { orderAddressId, customerAddressId } = value;

            if (!state.orderAddressIds.some(ids => ids.orderAddressId === orderAddressId)) {
                state.orderAddressIds.push(value);
                return;
            }

            state.orderAddressIds.forEach(ids => {
                if (ids.orderAddressId !== orderAddressId) {
                    return;
                }

                ids.customerAddressId = customerAddressId;
            });
        },
    },
};
