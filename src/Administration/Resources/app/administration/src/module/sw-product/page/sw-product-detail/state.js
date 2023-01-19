/*
 * @package inventory
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
            defaultFeatureSet: {},
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
                variants: false,
                defaultFeatureSet: false,
                advancedMode: false,
            },
            localMode: false,
            advancedModeSetting: {},
            modeSettings: [
                'general_information',
                'prices',
                'deliverability',
                'visibility_structure',
                'media',
                'labelling',
                'measures_packaging',
                'properties',
                'essential_characteristics',
                'custom_fields',
            ],
            /* Product "types" provided by the split button for creating a new product through a router parameter */
            creationStates: [],
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

        defaultFeatureSet(state) {
            if (!state.defaultFeatureSet) {
                return {};
            }

            return state.defaultFeatureSet;
        },

        productTaxRate(state) {
            if (!state.taxes) {
                return {};
            }

            return state.taxes.find((tax) => {
                if (!state.product.taxId) {
                    if (!state.parentProduct.taxId) {
                        return {};
                    }

                    return tax.id === state.parentProduct.taxId;
                }

                return tax.id === state.product.taxId;
            });
        },

        isChild(state) {
            if (state.product?.parentId) {
                return !!state.product.parentId;
            }
            return false;
        },

        showModeSetting(state) {
            if (state.product?.parentId) {
                return true;
            }

            return state.advancedModeSetting.value?.advancedMode.enabled;
        },

        showProductCard(state, getters) {
            return (key) => {
                if (state.product?.parentId) {
                    return true;
                }

                const cardKeys = ['essential_characteristics', 'custom_fields', 'labelling'];

                if (cardKeys.includes(key) && !getters.showModeSetting) {
                    return false;
                }

                return state.modeSettings.includes(key);
            };
        },

        advanceModeEnabled(state) {
            return state.advancedModeSetting.value?.advancedMode.enabled;
        },

        productStates(state) {
            if (state.product.isNew() && state.creationStates) {
                return state.creationStates;
            }

            if (state.product.states) {
                return state.product.states;
            }

            return [];
        },
    },

    mutations: {
        setApiContext(state, apiContext) {
            state.apiContext = apiContext;
        },

        setCustomFields(state, fieldSet) {
            state.customFieldSets = state.customFieldSets.map(set => {
                if (set.id === fieldSet.id) {
                    return fieldSet;
                }
                return set;
            });
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

        setAssignedProductsFromCrossSelling(state, { id, collection }) {
            const entity = state.product.crossSellings.get(id);
            entity.assignedProducts = collection;
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

            if (
                state.product &&
                state.product.taxId === null &&
                !state.parentProduct &&
                !state.parentProduct.id
            ) {
                state.product.taxId = state.taxes[0].id;
            }
        },

        setAttributeSet(state, newAttributeSets) {
            state.customFieldSets = newAttributeSets;
        },

        setDefaultFeatureSet(state, newDefaultFeatureSet) {
            state.defaultFeatureSet = newDefaultFeatureSet;
        },

        setAdvancedModeSetting(state, newAdvancedModeSetting) {
            state.advancedModeSetting = newAdvancedModeSetting;
        },

        setModeSettings(state, newModeSettings) {
            state.modeSettings = newModeSettings;
        },

        setCreationStates(state, states) {
            state.creationStates = states;
        },
    },
};
