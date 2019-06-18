import { State } from 'src/core/shopware';
import ErrorStore from 'src/core/data/error-store.data';
import { setReactive, deleteReactive } from 'src/app/adapter/view/vue.adapter';
import utils from 'src/core/service/util.service';

class VuexErrorStore {
    constructor() {
        this.state = {
            system: {},
            api: {}
        };

        this.mutations = {
            addApiError(state, { expression, error }) {
                ErrorStore.addApiError(expression, error, state, setReactive);
            },

            removeApiError(state, { expression }) {
                ErrorStore.removeApiError(expression, state, deleteReactive);
            },

            resetApiErrors(state) {
                ErrorStore.resetApiErrors(state);
            },

            addSystemError(state, { error, id = utils.createId() }) {
                ErrorStore.addSystemError(error, id, state, setReactive);
            },

            removeSystemError(state, { id }) {
                ErrorStore.removeSystemError(id, state, deleteReactive);
            }
        };

        this.getters = {
            getApiError: (state) => (entity, field) => {
                const path = [entity.getEntityName(), entity.id, field];
                return path.reduce((store, next) => {
                    if (store === null) {
                        return null;
                    }

                    if (store.hasOwnProperty(next)) {
                        return store[next];
                    }
                    return null;
                }, state.api);
            },

            getSystemError: (state) => (id) => {
                return state.system[id] || null;
            }
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
            }
        };
    }

    get $store() {
        if (typeof this._store === 'object') {
            return this._store;
        }

        this._store = State.getStore('vuex');
        return this._store;
    }

    addApiError(expression, error) {
        return this.$store.dispatch('addApiError', { expression, error });
    }

    addSystemError(error, id = utils.createId()) {
        return this.$store.dispatch('addSystemError', { error, id });
    }

    resetApiErrors() {
        return this.$store.dispatch('resetApiErrors');
    }
}

export default new VuexErrorStore();
