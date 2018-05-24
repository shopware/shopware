import { State, Application } from 'src/core/shopware';
import { deepCopyObject, getAssociatedDeletions, getObjectChangeSet } from 'src/core/service/utils/object.utils';
import { types } from 'src/core/service/util.service';

/**
 * @module app/state/manufacturer
 */
State.register('manufacturer', {
    namespaced: true,

    state() {
        return {
            // When entities are loaded, we keep a reference to the original version of the data.
            original: {},
            // For each entity we generate a copy which is the version where all changes are applied.
            draft: {}
        };
    },

    getters: {
        manufacturers(state) {
            return state.draft;
        }
    },

    actions: {
        /**
         * Get a list of manufacturers by offset and limit.
         *
         * @type action
         * @memberOf module:app/state/manufacturer
         * @param {Function} commit
         * @param {Number} offset
         * @param {Number} limit
         * @param {String} sortBy
         * @param {String} sortDirection
         * @param {String} term
         * @param {Array|null} criteria
         * @returns {Promise<T>}
         */
        getManufacturerList({ commit }, { limit, offset, sortBy, sortDirection, term, criteria }) {
            const providerContainer = Application.getContainer('service');
            const manufacturerService = providerContainer.productManufacturerService;

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

            return manufacturerService.getList(offset, limit, additionalParams).then((response) => {
                const manufacturers = response.data;
                const total = response.meta.total;

                manufacturers.forEach((manufacturer) => {
                    commit('initManufacturer', manufacturer);
                });

                return {
                    manufacturers,
                    total
                };
            });
        },

        /**
         * Get a manufacturer by id.
         * If the manufacturer does not exist in the state object, it will be loaded via the API.
         *
         * @type action
         * @memberOf module:app/state/manufacturer
         * @param {Function} commit
         * @param {Object} state
         * @param {String} id
         * @param {Boolean} [localCopy=false]
         * @returns {Promise<T>|String}
         */
        getManufacturerById({ commit, state }, id, localCopy = false) {
            const manufacturer = state.draft[id];

            if (typeof manufacturer !== 'undefined' && manufacturer.isDetail) {
                return (localCopy === true) ? deepCopyObject(manufacturer) : manufacturer;
            }

            const providerContainer = Application.getContainer('service');
            const manufacturerService = providerContainer.productManufacturerService;

            return manufacturerService.getById(id).then((response) => {
                const loadedManufacturer = response.data;
                loadedManufacturer.isDetail = true;

                commit('initManufacturer', loadedManufacturer);

                return (localCopy === true) ? deepCopyObject(state.draft[id]) : state.draft[id];
            });
        },

        /**
         * Saves the given manufacturer to the server by sending a changeset.
         *
         * @type action
         * @memberOf module:app/state/manufacturer
         * @param {Function} commit
         * @param {Object} state
         * @param {Object} product
         * @return {Promise}
         */
        saveManufacturer({ commit, state }, manufacturer) {
            if (!manufacturer.id) {
                return Promise.reject();
            }

            const providerContainer = Application.getContainer('service');
            const manufacturerService = providerContainer.productManufacturerService;

            console.log(manufacturerService);

            const changeset = getObjectChangeSet(state.original[manufacturer.id], manufacturer, 'product_manufacturer');
            const deletions = getAssociatedDeletions(state.original[manufacturer.id], manufacturer, 'product_manufacturer');

            const deletionCue = [];

            if (!types.isEmpty(deletions)) {
                Object.keys(deletions).forEach((property) => {
                    if (types.isArray(deletions[property])) {
                        deletions[property].forEach((association) => {
                            deletionCue.push(new Promise((resolve, reject) => {
                                manufacturerService.deleteAssociation(manufacturer.id, property, association.id)
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

                if (manufacturer.isNew) {
                    return manufacturerService.create(changeset)
                        .then((response) => {
                            const newProduct = response.data;

                            commit('initManufacturer', newProduct);
                            return newProduct;
                        })
                        .catch((exception) => {
                            if (exception.response.data && exception.response.data.errors) {
                                exception.response.data.errors.forEach((error) => {
                                    commit('addManufacturerError', error);
                                });
                            }

                            return exception;
                        });
                }

                return manufacturerService.updateById(manufacturer.id, changeset)
                    .then((response) => {
                        commit('initManufacturer', response.data);
                        return response.data;
                    })
                    .catch((exception) => {
                        if (exception.response.data && exception.response.data.errors) {
                            exception.response.data.errors.forEach((error) => {
                                commit('addManufacturerError', error);
                            });
                        }

                        return exception;
                    });
            }).catch((deleteException) => {
                if (deleteException.response.data && deleteException.response.data.errors) {
                    deleteException.response.data.errors.forEach((error) => {
                        commit('addManufacturerError', error);
                    });
                }

                return deleteException;
            });
        }
    },

    mutations: {
        initManufacturer(state, manufacturer) {
            if (!manufacturer.id) {
                return;
            }

            const originalManufacturer = deepCopyObject(manufacturer);
            const draftManufacturer = deepCopyObject(manufacturer);

            state.original[manufacturer.id] = Object.assign(state.original[manufacturer.id] || {}, originalManufacturer);
            state.draft[manufacturer.id] = Object.assign(state.draft[manufacturer.id] || {}, draftManufacturer);
        },

        /**
         * Commits a manufacturer error to the global error state.
         *
         * @memberOf module:app/state/manufacturer
         * @param state
         * @param error
         */
        addManufacturerError(state, error) {
            this.commit('error/addError', {
                module: 'manufacturer',
                error
            });
        }
    }
});
