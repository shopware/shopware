import { State } from 'src/core/shopware';
import utils, { types } from 'src/core/service/util.service';
import { deepCopyObject, getObjectChangeSet } from 'src/core/service/utils/object.utils';

/**
 * @module app/state/product
 */
State.register('product', {
    namespaced: true,
    /**
     * VueJS now also throws an error when changing sub properties on the state directly.
     * This does not work with our current concept of the watchers.
     *
     * ToDo: Evaluate strict state handling with large object structures.
     */
    // strict: true,

    state() {
        return {
            // When entities are loaded, we keep a reference to the original version of the data.
            original: {},
            // For each entity we generate a copy which is the version where all changes are applied.
            draft: {}
        };
    },

    getters: {
        products(state) {
            return state.draft;
        }
    },

    actions: {
        /**
         * Get a list of products by offset and limit.
         *
         * @type action
         * @memberOf module:app/state/product
         * @param {Function} commit
         * @param {Number} offset
         * @param {Number} limit
         * @param {String} sortBy
         * @param {String} sortDirection
         * @param {String} term
         * @param {Array|null} criterias
         * @returns {Promise<T>}
         */
        getProductList({ commit }, { limit, offset, sortBy, sortDirection, term, criterias }) {
            const providerContainer = Shopware.Application.getContainer('service');
            const productService = providerContainer.productService;

            const additionalParams = {};

            if (sortBy && sortBy.length) {
                additionalParams.sort = (sortDirection.toLowerCase() === 'asc' ? '' : '-') + sortBy;
            }

            if (term) {
                additionalParams.term = term;
            }

            if (criterias) {
                additionalParams.filter = criterias;
            }

            return productService.getList(offset, limit, additionalParams).then((response) => {
                const products = response.data;
                const total = response.meta.total;

                products.forEach((product) => {
                    commit('initProduct', product);
                });

                return {
                    products,
                    total
                };
            });
        },

        /**
         * Get a product by id.
         * If the product does not exist in the state object, it will be loaded via the API.
         *
         * @type action
         * @memberOf module:app/state/product
         * @param {Function} commit
         * @param {Object} state
         * @param {String} id
         * @param {Boolean} [localCopy=false]
         * @returns {Promise<T>|String}
         */
        getProductById({ commit, state }, id, localCopy = false) {
            const product = state.draft[id];

            if (typeof product !== 'undefined' && product.isDetail) {
                return (localCopy === true) ? deepCopyObject(product) : product;
            }

            const providerContainer = Shopware.Application.getContainer('service');
            const productService = providerContainer.productService;

            return productService.getById(id).then((response) => {
                const loadedProduct = response.data;
                loadedProduct.isDetail = true;

                commit('initProduct', loadedProduct);

                return (localCopy === true) ? deepCopyObject(state.draft[id]) : state.draft[id];
            });
        },

        /**
         * Create an empty product object with all possible properties from the entity definition.
         * The object can be used in the data binding for creating a new product.
         * It will be marked with a `ìsNew` property.
         *
         * @type action
         * @memberOf module:app/state/product
         * @param {Function} commit
         * @param {Object} state
         * @param {String|null} [productId=null]
         * @returns {String|null}
         */
        createEmptyProduct({ commit, state }, productId = null) {
            if (productId === null) {
                productId = utils.createId();
            }

            if (typeof state.draft[productId] !== 'undefined') {
                return state.draft[productId];
            }

            const product = Shopware.Entity.getRawEntityObject('product', true);

            product.id = productId;
            product.isDetail = true;
            product.isNew = true;

            commit('initProduct', product);

            return productId;
        },

        /**
         * Saves the given product to the server by sending a changeset.
         *
         * @type action
         * @memberOf module:app/state/product
         * @param {Function} commit
         * @param {Object} state
         * @param {Object} product
         * @returns {Promise<T>}
         */
        saveProduct({ commit, state }, product) {
            if (!product.id) {
                return false;
            }

            const providerContainer = Shopware.Application.getContainer('service');
            const productService = providerContainer.productService;

            const changeset = getObjectChangeSet(state.original[product.id], product, 'product');

            if (types.isEmpty(changeset)) {
                return false;
            }

            if (product.isNew) {
                return productService.create(changeset).then((response) => {
                    const newProduct = response.data;

                    commit('initProduct', newProduct);
                    return newProduct;
                }).catch((exception) => {
                    if (exception.response.data && exception.response.data.errors) {
                        exception.response.data.errors.forEach((error) => {
                            commit('addProductError', error);
                        });
                    }

                    return Promise.reject(exception);
                });
            }

            return productService.updateById(product.id, changeset).then((response) => {
                commit('initProduct', response.data);
                return response.data;
            }).catch((exception) => {
                if (exception.response.data && exception.response.data.errors) {
                    exception.response.data.errors.forEach((error) => {
                        commit('addProductError', error);
                    });
                }

                return Promise.reject(exception);
            });
        }
    },

    mutations: {
        /**
         * Initializes a new product in the state.
         *
         * @type mutation
         * @memberOf module:app/state/product
         * @param {Object} state
         * @param {Object} product
         * @returns {void}
         */
        initProduct(state, product) {
            // Do not commit products without identifier
            if (!product.id) {
                return;
            }

            product.contextPrices.sort((a, b) => {
                if (a.quantityStart < b.quantityStart) {
                    return -1;
                }

                if (a.quantityStart > b.quantityStart) {
                    return 1;
                }

                return 0;
            });

            const originalProduct = deepCopyObject(product);
            const draftProduct = deepCopyObject(product);

            product.isLoaded = true;
            state.original[product.id] = Object.assign(state.original[product.id] || {}, originalProduct);
            state.draft[product.id] = Object.assign(state.draft[product.id] || {}, draftProduct);
        },

        /**
         * Updates a product in the state.
         *
         * @type mutation
         * @memberOf module:app/state/product
         * @param {Object} state
         * @param {Object} product
         * @returns {void}
         */
        setProduct(state, product) {
            // Do not commit products without identifier
            if (!product.id) {
                return;
            }

            Object.assign(state.draft[product.id], product);
        },

        /**
         * Commits a product error to the global error state.
         *
         * @memberOf module:app/state/product
         * @param state
         * @param error
         */
        addProductError(state, error) {
            this.commit('error/addError', {
                module: 'product',
                error
            });
        }
    }
});
