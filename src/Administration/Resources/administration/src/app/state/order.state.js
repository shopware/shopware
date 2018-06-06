import { State } from 'src/core/shopware';
import { deepCopyObject } from 'src/core/service/utils/object.utils';

/**
 * @module app/state/order
 */
State.register('order', {
    namespaced: true,

    state() {
        return {
            original: {},
            draft: {}
        };
    },

    getters: {
        orders(state) {
            return state.draft;
        }
    },

    actions: {
        /**
         * Get a list of orders by offset and limit.
         *
         * @type action
         * @memberOf module:app/state/order
         * @param {Function} commit
         * @param {Number} offset
         * @param {Number} limit
         * @param {String} sortBy
         * @param {String} sortDirection
         * @param {String} term
         * @param {Array|null} criteria
         * @returns {Promise<T>}
         */
        getOrderList({ commit }, { limit, offset, sortBy, sortDirection, term, criteria }) {
            const providerContainer = Shopware.Application.getContainer('service');
            const orderService = providerContainer.orderService;

            const additionalParams = {};

            if (sortBy && sortBy.length) {
                additionalParams.sort = (sortDirection.toLowerCase() === 'asc' ? '' : '-') + sortBy;
            }

            if (term) {
                additionalParams.term = term;
            }

            if (criteria) {
                additionalParams.filter = criteria;
            }

            return orderService.getList(offset, limit, additionalParams).then((response) => {
                const orders = response.data;
                const total = response.meta.total;

                orders.forEach((order) => {
                    commit('initOrder', order);
                });

                return {
                    orders,
                    total
                };
            });
        },

        /**
         * Get an order by id.
         * If the order does not exist in the state object, it will be loaded via the API.
         *
         * @type action
         * @memberOf module:app/state/order
         * @param {Function} commit
         * @param {Object} state
         * @param {String} id
         * @param {Boolean} [localCopy=false]
         * @returns {Promise<T>|String}
         */
        getOrderById({ commit, state }, id, localCopy = false) {
            const order = state.draft[id];

            if (typeof order !== 'undefined' && order.isDetail) {
                return (localCopy === true) ? deepCopyObject(order) : order;
            }

            const providerContainer = Shopware.Application.getContainer('service');
            const orderService = providerContainer.orderService;

            return orderService.getById(id).then((response) => {
                const loadedOrder = response.data;
                loadedOrder.isDetail = true;

                commit('initOrder', loadedOrder);

                return (localCopy === true) ? deepCopyObject(state.draft[id]) : state.draft[id];
            });
        }
    },

    mutations: {
        /**
         * Initializes a new order in the state.
         *
         * @type mutation
         * @memberOf module:app/state/order
         * @param {Object} state
         * @param {Object} order
         * @returns {void}
         */
        initOrder(state, order) {
            if (!order.id) {
                return;
            }

            const originalOrder = deepCopyObject(order);
            const draftOrder = deepCopyObject(order);

            order.isLoaded = true;
            state.original[order.id] = Object.assign(state.original[order.id] || {}, originalOrder);
            state.draft[order.id] = Object.assign(state.draft[order.id] || {}, draftOrder);
        },

        /**
         * Updates an order in the state.
         *
         * @type mutation
         * @memberOf module:app/state/order
         * @param {Object} state
         * @param {Object} order
         * @returns {void}
         */
        setOrder(state, order) {
            if (!order.id) {
                return;
            }

            Object.assign(state.draft[order.id], order);
        },

        /**
         * Commits an order error to the global error state.
         *
         * @memberOf module:app/state/order
         * @param state
         * @param error
         */
        addOrderError(state, error) {
            this.commit('error/addError', {
                module: 'order',
                error
            });
        }
    }
});
