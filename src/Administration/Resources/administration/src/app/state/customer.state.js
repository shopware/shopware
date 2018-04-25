import { State } from 'src/core/shopware';
import utils, { types } from 'src/core/service/util.service';
import { deepCopyObject, getAssociatedDeletions, getObjectChangeSet } from 'src/core/service/utils/object.utils';

/**
 * @module app/state/customer
 */
State.register('customer', {
    namespaced: true,

    state() {
        return {
            original: {},
            draft: {},
            editMode: false
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
         * Get a customer by id.
         * If the customer does not exist in the state object, it will be loaded via the API.
         *
         * @type action
         * @memberOf module:app/state/customer
         * @param {Function} commit
         * @param {Object} state
         * @param {String} id
         * @param {Boolean} [localCopy=false]
         * @returns {Promise<T>|String}
         */
        getCustomerById({ commit, state }, id, localCopy = false) {
            const customer = state.draft[id];

            if (typeof customer !== 'undefined' && customer.isDetail) {
                return (localCopy === true) ? deepCopyObject(customer) : customer;
            }

            const providerContainer = Shopware.Application.getContainer('service');
            const customerService = providerContainer.customerService;

            return customerService.getById(id).then((response) => {
                const loadedCustomer = response.data;
                loadedCustomer.isDetail = true;

                commit('initCustomer', loadedCustomer);

                return (localCopy === true) ? deepCopyObject(state.draft[id]) : state.draft[id];
            });
        },

        /**
         * Create an empty customer object with all possible properties from the entity definition.
         * The object can be used in the data binding for creating a new customer.
         * It will be marked with a `ìsNew` property.
         *
         * @type action
         * @memberOf module:app/state/customer
         * @param {Function} commit
         * @param {Object} state
         * @param {String|null} [customerId=null]
         * @returns {String|null}
         */
        createEmptyCustomer({ commit, state }, customerId = null) {
            if (customerId === null) {
                customerId = utils.createId();
            }

            if (typeof state.draft[customerId] !== 'undefined') {
                return state.draft[customerId];
            }

            const customer = Shopware.Entity.getRawEntityObject('customer', true);

            customer.id = customerId;
            customer.isDetail = true;
            customer.isNew = true;
            console.log('le customer', customer);

            commit('initCustomer', customer);

            return customerId;
        },

        /**
         * Saves the given customer to the server by sending a changeset.
         *
         * @type action
         * @memberOf module:app/state/customer
         * @param {Function} commit
         * @param {Object} state
         * @param {Object} customer
         * @return {Promise<T>}
         */
        saveCustomer({ commit, state }, customer) {
            console.log(customer.id);

            if (!customer.id) {
                return Promise.reject();
            }

            const providerContainer = Shopware.Application.getContainer('service');
            const customerService = providerContainer.customerService;

            const changeset = getObjectChangeSet(state.original[customer.id], customer, 'customer');
            const deletions = getAssociatedDeletions(state.original[customer.id], customer, 'customer');

            // @Todo: Just a test
            changeset.defaultShippingAddress = {
                id: utils.createId(),
                firstName: 'foo',
                lastName: 'foo',
                city: 'foo',
                street: 'foo',
                zipcode: '2312',
                salutation: 'foo',
                country: {
                    name: 'foo'
                }
            };
            changeset.defaultShippingAddressId = changeset.defaultShippingAddress.id;
            changeset.defaultBillingAddressId = changeset.defaultShippingAddress.id;
            changeset.shopId = utils.createId();
            changeset.defaultPaymentMethodId = utils.createId();
            changeset.groupId = utils.createId();
            changeset.password = 'shopware';

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
                                        console.log('YAAAAAAAAAAAAAAAAAAAAAAAAAAAAY4');
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
                            return Promise.reject(exception);
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

                        return Promise.reject(exception);
                    });
            }).catch((deleteException) => {
                if (deleteException.response.data && deleteException.response.data.errors) {
                    deleteException.response.data.errors.forEach((error) => {
                        commit('addCustomerError', error);
                    });
                }

                return Promise.reject(deleteException);
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
         * Updates a customer in the state.
         *
         * @type mutation
         * @memberOf module:app/state/customer
         * @param {Object} state
         * @param {Object} customer
         * @returns {void}
         */
        setCustomer(state, customer) {
            if (!customer.id) {
                return;
            }

            Object.assign(state.draft[customer.id], customer);
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
