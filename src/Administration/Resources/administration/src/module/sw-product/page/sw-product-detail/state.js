import Criteria from 'src/core/data-new/criteria.data';

// Criterias
const defaultCriteria = () => {
    return new Criteria(1, 500);
};

const mediaCriteria = () => {
    const criteria = new Criteria(1, 50);
    criteria.addSorting(
        Criteria.sort('position', 'ASC')
    );
    return criteria;
};

const tagsCriteria = () => {
    const criteria = new Criteria(1, 500);
    criteria.addSorting(
        Criteria.sort('name', 'ASC')
    );
    return criteria;
};

const pricesCriteria = () => {
    const criteria = new Criteria(1, 500);
    criteria.addSorting(
        Criteria.sort('quantityStart', 'ASC', true)
    );
    return criteria;
};

const visibilitiesCriteria = () => {
    const criteria = new Criteria(1, 500);
    criteria.addAssociation('salesChannel', defaultCriteria());

    return criteria;
};

const propertyCriteria = () => {
    const criteria = new Criteria(1, 500);
    criteria.addSorting(
        Criteria.sort('name', 'ASC')
    );
    return criteria;
};

const productCriteria = () => {
    const criteria = new Criteria();
    criteria.addAssociation('media', mediaCriteria());
    criteria.addAssociation('properties', propertyCriteria());
    criteria.addAssociation('visibilities', visibilitiesCriteria());
    criteria.addAssociation('prices', pricesCriteria());
    criteria.addAssociation('tags', tagsCriteria());
    return criteria;
};

const customFieldCriteria = () => {
    const criteria = new Criteria(1, 100);
    criteria.addSorting(
        Criteria.sort('config.customFieldPosition')
    );
    return criteria;
};

const customFieldSetCriteria = () => {
    const criteria = new Criteria(1, 100);
    criteria
        .addFilter(
            Criteria.equals('relations.entityName', 'product')
        )
        .addAssociation('customFields', customFieldCriteria());
    return criteria;
};


// Store
export default {
    namespaced: true,

    state() {
        return {
            repositoryFactory: null,
            product: {},
            productId: '',
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
            debouncedLoadingTimer: null,
            localMode: false
        };
    },

    getters: {
        isLoading: (state) => {
            return Object.values(state.loading).some((loadState) => loadState);
        },

        getProduct: (state) => {
            return state.product;
        },

        productRepository(state) {
            return state.repositoryFactory ? state.repositoryFactory.create('product') : null;
        },

        currencyRepository(state) {
            return state.repositoryFactory ? state.repositoryFactory.create('currency') : null;
        },

        taxRepository(state) {
            return state.repositoryFactory ? state.repositoryFactory.create('tax') : null;
        },

        customFieldSetRepository(state) {
            return state.repositoryFactory ? state.repositoryFactory.create('custom_field_set') : null;
        },

        mediaRepository(state) {
            if (state.product && state.product.media) {
                return state.repositoryFactory.create(
                    state.product.media.entity,
                    state.product.media.source
                );
            }
            return null;
        },

        languageRepository(state) {
            return state.repositoryFactory ? state.repositoryFactory.create('language') : null;
        },

        hasChanges(state, getters) {
            if (Object.values(state.product).length <= 0) {
                return null;
            }

            if (!state.product.getEntityName) {
                return null;
            }

            return getters.productRepository.hasChanges(state.product, state.context);
        }
    },

    mutations: {
        setRepositoryFactory(state, repositoryFactory) {
            state.repositoryFactory = repositoryFactory;
        },

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
        },

        removeMediaItem(state, mediaId) {
            state.product.media.remove(mediaId);
        }
    },

    actions: {
        initState({ commit }, { productId, repositoryFactory, context }) {
            // Init dependencies
            if (productId) {
                commit('setProductId', productId);
            }
            commit('setRepositoryFactory', repositoryFactory);
            commit('setContext', context);
        },

        loadState({ dispatch, commit }, { productId, repositoryFactory, context }) {
            dispatch('initState', { productId, repositoryFactory, context });

            // disable local mode
            commit('setLocalMode', false);

            return dispatch('loadAll');
        },

        createState({ dispatch }, { repositoryFactory, context }) {
            dispatch('initState', { productId: '', repositoryFactory, context });
            return dispatch('createProduct');
        },

        createProduct({ state, getters, commit, dispatch }) {
            commit('setLoading', ['product', true]);

            // create empty product
            commit('setProduct', getters.productRepository.create(state.context));

            // set local mode
            commit('setLocalMode', true);

            // fill empty data
            state.product.price = {
                net: null,
                linked: true,
                gross: null,
                extensions: []
            };

            state.product.active = true;
            state.productId = state.product.id;
            state.product.taxId = null;

            state.product.metaTitle = '';
            state.product.additionalText = '';

            return Promise.all([
                dispatch('loadCurrencies'),
                dispatch('loadTaxes'),
                dispatch('loadAttributeSet')
            ]).then(() => {
                commit('setLoading', ['product', false]);
            });
        },

        loadAll({ dispatch }) {
            return Promise.all([
                dispatch('loadProduct'),
                dispatch('loadCurrencies'),
                dispatch('loadTaxes'),
                dispatch('loadAttributeSet')
            ]);
        },

        loadProduct({ state, commit, getters }) {
            commit('setLoading', ['product', true]);

            return getters.productRepository.get(state.productId, state.context, productCriteria()).then((res) => {
                commit('setProduct', res);
            }).then(() => {
                commit('setLoading', ['product', false]);
            });
        },

        loadCurrencies({ state, commit, getters }) {
            commit('setLoading', ['currencies', true]);

            return getters.currencyRepository.search(defaultCriteria(), state.context).then((res) => {
                commit('setCurrencies', res);
            }).then(() => {
                commit('setLoading', ['currencies', false]);
            });
        },

        loadTaxes({ state, commit, getters }) {
            commit('setLoading', ['taxes', true]);

            return getters.taxRepository.search(defaultCriteria(), state.context).then((res) => {
                commit('setTaxes', res);
            }).then(() => {
                commit('setLoading', ['taxes', false]);
            });
        },

        loadAttributeSet({ state, commit, getters }) {
            commit('setLoading', ['customFieldSets', true]);

            return getters.customFieldSetRepository.search(customFieldSetCriteria(), state.context).then((res) => {
                commit('setAttributeSet', res);
            }).then(() => {
                commit('setLoading', ['customFieldSets', false]);
            });
        },

        saveProduct({ state, commit, getters, dispatch }) {
            commit('setLoading', ['product', true]);

            return new Promise((resolve) => {
                new Promise((childResolve) => {
                    // check if product exists
                    if (!getters.productRepository.hasChanges(state.product)) {
                        childResolve('empty');
                        return;
                    }

                    // save product
                    getters.productRepository.save(state.product, state.context).then(() => {
                        dispatch('loadAll').then(() => {
                            childResolve('success');
                        });
                    }).catch((response) => {
                        childResolve(response);
                    });
                }).then((res) => {
                    commit('setLoading', ['product', false]);
                    resolve(res);
                });
            });
        },

        addMedia({ state, commit, getters }, mediaItem) {
            commit('setLoading', ['media', true]);

            // return error if media exists
            if (state.product.media.has(mediaItem.id)) {
                commit('setLoading', ['media', false]);
                // eslint-disable-next-line prefer-promise-reject-errors
                return Promise.reject('A media item with this id exists');
            }

            const newMedia = getters.mediaRepository.create(state.context, mediaItem.id);
            newMedia.mediaId = mediaItem.id;

            return new Promise((resolve) => {
                state.product.media.add(newMedia);

                commit('setLoading', ['media', false]);

                resolve(newMedia.mediaId);
                return true;
            });
        }
    }
};
