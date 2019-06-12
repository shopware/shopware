export default {
    namespaced: true,

    state() {
        return {
            product: {},
            currencies: {},
            context: {},
            taxes: {},
            customFieldSets: {},
            loading: {
                product: false,
                manufacturers: false,
                currencies: false,
                taxes: false,
                customFieldSets: false,
                media: false,
                rules: false
            },
            localMode: false
        };
    },

    getters: {
        isLoading: (state) => {
            return Object.values(state.loading).some((loadState) => loadState);
        }
    },

    mutations: {
        setContext(state, context) {
            state.context = context;
        },

        setLocalMode(state, value) {
            state.localMode = value;
        },

        setLoading(state, value) {
            const name = value[0];
            const data = value[1];

            if (typeof data !== 'boolean') {
                return false;
            }

            if (state.loading[name] !== undefined) {
                state.loading[name] = data;
                return true;
            }
            return false;
        },

        setProductId(state, productId) {
            state.productId = productId;
        },

        setProduct(state, newProduct) {
            state.product = newProduct;
        },

        setCurrencies(state, newCurrencies) {
            state.currencies = newCurrencies;
        },

        setTaxes(state, newTaxes) {
            state.taxes = newTaxes;

            if (state.product && state.product.taxId === null) {
                state.product.taxId = Object.values(state.taxes.items)[0].id;
            }
        },

        setAttributeSet(state, newAttributeSets) {
            state.customFieldSets = newAttributeSets;
        }
    }
};
