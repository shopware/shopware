/**
 * @package admin
 */

const { Application } = Shopware;
const { ErrorStore } = Shopware.Data;
const utils = Shopware.Utils;

class VuexErrorStore {
    constructor() {
        this.namespaced = true;

        this.state = {
            system: {},
            api: {},
        };

        this.mutations = {
            addApiError(state, { expression, error }) {
                error.selfLink = expression;
                ErrorStore.addApiError(expression, error, state, Application.view.setReactive);
            },

            removeApiError(state, { expression }) {
                ErrorStore.removeApiError(expression, state, Application.view.deleteReactive);
            },

            resetApiErrors(state) {
                ErrorStore.resetApiErrors(state);
            },

            addSystemError(state, { error, id = utils.createId() }) {
                ErrorStore.addSystemError(error, id, state, Application.view.setReactive);
            },

            removeSystemError(state, { id }) {
                ErrorStore.removeSystemError(id, state, Application.view.deleteReactive);
            },
        };

        this.getters = {
            getErrorsForEntity: (state) => (entityName, id) => {
                const entityStorage = state.api[entityName];
                if (!entityStorage) {
                    return null;
                }

                return entityStorage[id] || null;
            },

            getApiErrorFromPath: (state, getters) => (entityName, id, path) => {
                return path.reduce((store, next) => {
                    if (store === null) {
                        return null;
                    }

                    if (store.hasOwnProperty(next)) {
                        return store[next];
                    }
                    return null;
                }, getters.getErrorsForEntity(entityName, id));
            },

            getApiError: (state, getters) => (entity, field) => {
                const path = field.split('.');
                return getters.getApiErrorFromPath(entity.getEntityName(), entity.id, path);
            },

            getSystemConfigApiError: (state, getters) => (entityName, saleChannelId, key) => {
                const errors = getters.getErrorsForEntity(entityName, saleChannelId);

                if (!errors) {
                    return null;
                }

                if (errors.hasOwnProperty(key)) {
                    return errors[key];
                }

                return null;
            },

            getAllApiErrors: (state) => () => {
                return Object.values(state.api);
            },

            existsErrorInProperty: (state) => (entity, properties) => {
                const entityErrors = state.api[entity];
                if (!entityErrors) {
                    return false;
                }

                return Object.keys(entityErrors).some((id) => {
                    const instance = entityErrors[id];
                    return Object.keys(instance).some((propWithError) => {
                        return properties.includes(propWithError);
                    });
                });
            },

            getSystemError: (state) => (id) => {
                return state.system[id] || null;
            },

            countSystemError: (state) => () => {
                return Object.keys(state.system).length;
            },
        };

        this.actions = {
            addApiError({ commit }, { expression, error }) {
                commit('addApiError', { expression, error });
            },

            removeApiError({ commit }, { expression }) {
                commit('removeApiError', { expression });
            },

            resetApiErrors({ commit }) {
                commit('resetApiErrors');
            },

            addSystemError({ commit }, { error, id = utils.createId() }) {
                commit('addSystemError', { error, id });
                return id;
            },

            removeSystemError({ commit }, { id }) {
                commit('removeSystemError', { id });
            },
        };
    }
}

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default new VuexErrorStore();
