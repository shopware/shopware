export default {
    namespaced: true,

    state() {
        return {
            product: {},
            parentProduct: {},
            currencies: [],
            apiContext: {},
            taxes: [],
            variants: [],
            customFieldSets: [],
            loading: {
                init: false,
                product: false,
                parentProduct: false,
                manufacturers: false,
                currencies: false,
                taxes: false,
                customFieldSets: false,
                media: false,
                rules: false,
                variants: false
            },
            localMode: false
        };
    },

    getters: {
        isLoading: (state) => {
            return Object.values(state.loading).some((loadState) => loadState);
        },

        defaultCurrency(state) {
            if (!state.currencies) {
                return {};
            }

            const defaultCurrency = state.currencies.find((currency) => currency.isSystemDefault);

            return defaultCurrency || {};
        },

        defaultPrice(state, getters) {
            let productPrice = state.product.price;

            // check if price exists
            if (!productPrice) {
                // if parent price does not exists
                if (!state.parentProduct.price) {
                    return {};
                }

                productPrice = state.parentProduct.price;
            }

            // get default price bases on currency
            return productPrice.find((price) => {
                return price.currencyId === getters.defaultCurrency.id;
            });
        },

        productTaxRate(state) {
            if (!state.taxes) {
                return {};
            }

            return state.taxes.find((tax) => {
                return tax.id === state.product.taxId;
            });
        },

        isChild(state) {
            if (state.product && state.product.parentId) {
                return !!state.product.parentId;
            }
            return false;
        }
    },

    mutations: {
        setApiContext(state, apiContext) {
            state.apiContext = apiContext;
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

        setVariants(state, newVariants) {
            state.variants = newVariants;
        },

        setParentProduct(state, newProduct) {
            state.parentProduct = newProduct;
        },

        setCurrencies(state, newCurrencies) {
            state.currencies = newCurrencies;
        },

        setTaxes(state, newTaxes) {
            state.taxes = newTaxes;

            if (state.product && state.product.taxId === null) {
                state.product.taxId = state.taxes[0].id;
            }
        },

        setAttributeSet(state, newAttributeSets) {
            state.customFieldSets = newAttributeSets;
        }
    }
};
