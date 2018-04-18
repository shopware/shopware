import { State } from 'src/core/shopware';
import { types } from 'src/core/service/util.service';
import { deepCopyObject, getAssociatedDeletions, getObjectChangeSet } from 'src/core/service/utils/object.utils';

/**
 * @module app/state/customer
 */
State.register('customer', {
    namespaced: true,

    state() {
        return {
            original: {},
            draft: {}
        };
    },

    getters: {
        customers(state) {
            return state.draft;
        }
    },

    actions: {
        /**
         * Get a list of customers by offset and limit.
         *
         * @type action
         * @memberOf module:app/state/customer
         * @param {Function} commit
         * @param {Number} offset
         * @param {Number} limit
         * @param {String} sortBy
         * @param {String} sortDirection
         * @param {String} term
         * @param {Array|null} criteria
         * @returns {Promise<T>}
         */
        getCustomerList({ commit }, { limit, offset, sortBy, sortDirection, term, criteria }) {
            const providerContainer = Shopware.Application.getContainer('service');
            const customerService = providerContainer.customerService;

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

            return customerService.getList(offset, limit, additionalParams).then((response) => {
                const customers = response.data;
                const total = response.meta.total;

                customers.forEach((customer) => {
                    commit('initCustomer', customer);
                });

                return {
                    customers,
                    total
                };
            });
        },

        /**
         * Saves the given customer to the server by sending a changeset.
         *
         * @type action
         * @memberOf module:app/state/customer
         * @param {Function} commit
         * @param {Object} state
         * @param {Object} customer
         * @return {Promise}
         */
        saveCustomer({ commit, state }, customer) {
            if (!customer.id) {
                return Promise.reject();
            }

            const providerContainer = Shopware.Application.getContainer('service');
            const customerService = providerContainer.customerService;

            const changeset = getObjectChangeSet(state.original[customer.id], customer, 'customer');
            const deletions = getAssociatedDeletions(state.original[customer.id], customer, 'customer');

            const deletionCue = [];

            if (!types.isEmpty(deletions)) {
                Object.keys(deletions).forEach((property) => {
                    if (types.isArray(deletions[property])) {
                        deletions[property].forEach((association) => {
                            deletionCue.push(new Promise((resolve, reject) => {
                                customerService.deleteAssociation(customer.id, property, association.id)
                                    .then((response) => {
                                        resolve(response);
                                    })
                                    .catch((response) => {
                                        reject(response);
                                    });
                            }));
                        });
                    }
                });
            }

            return Promise.all(deletionCue).then((deleteResponse) => {
                if (types.isEmpty(changeset)) {
                    return deleteResponse;
                }

                if (customer.isNew) {
                    return customerService.create(changeset)
                        .then((response) => {
                            const newCustomer = response.data;

                            commit('initCustomer', newCustomer);
                            return newCustomer;
                        })
                        .catch((exception) => {
                            if (exception.response.data && exception.response.data.errors) {
                                exception.response.data.errors.forEach((error) => {
                                    commit('addCustomerError', error);
                                });
                            }

                            return exception;
                        });
                }

                return customerService.updateById(customer.id, changeset)
                    .then((response) => {
                        commit('initCustomer', response.data);
                        return response.data;
                    })
                    .catch((exception) => {
                        if (exception.response.data && exception.response.data.errors) {
                            exception.response.data.errors.forEach((error) => {
                                commit('addCustomerError', error);
                            });
                        }

                        return exception;
                    });
            }).catch((deleteException) => {
                if (deleteException.response.data && deleteException.response.data.errors) {
                    deleteException.response.data.errors.forEach((error) => {
                        commit('addCustomerError', error);
                    });
                }

                return deleteException;
            });
        }
    },

    mutations: {
        /**
         * Initializes a new customer in the state.
         *
         * @type mutation
         * @memberOf module:app/state/customer
         * @param {Object} state
         * @param {Object} customer
         * @returns {void}
         */
        initCustomer(state, customer) {
            if (!customer.id) {
                return;
            }

            const originalCustomer = deepCopyObject(customer);
            const draftCustomer = deepCopyObject(customer);

            customer.isLoaded = true;
            state.original[customer.id] = Object.assign(state.original[customer.id] || {}, originalCustomer);
            state.draft[customer.id] = Object.assign(state.draft[customer.id] || {}, draftCustomer);
        },

        /**
         * Commits a customer error to the global error state.
         *
         * @memberOf module:app/state/customer
         * @param state
         * @param error
         */
        addCustomerError(state, error) {
            this.commit('error/addError', {
                module: 'customer',
                error
            });
        }
    }
});
