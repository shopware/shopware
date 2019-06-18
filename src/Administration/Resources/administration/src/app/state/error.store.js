import ErrorStore from 'src/core/data/error-store.data';
import { setReactive, deleteReactive } from 'src/app/adapter/view/vue.adapter';

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

            resetError() { // (state, { expression }) {
                // TODO refactor with fields.....
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

            resetFormError({ commit }, expression) {
                commit('resetError', { expression, type: 'api' });
            }
        };
    }

    addApiError(expression, error) {
        this.mutations.addApiError(this.state, { expression, error });
    }
}

export default new VuexErrorStore();
