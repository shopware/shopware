import ErrorStore from 'src/core/data/ErrorStore';
import ShopwareError from 'src/core/data/ShopwareError';
import { setReactive, deleteReactive } from 'src/app/adapter/view/vue.adapter';

class VuexErrorStore extends ErrorStore {
    constructor() {
        super();

        this.state = this.errors;

        this.mutations = {
            setErrorData(state, { expression, error, type }) {
                const { errorName, container } = ErrorStore.getErrorContainer(expression, state[type]);
                if (container !== null) {
                    container[errorName] = error;
                }
            },

            createError(state, { expression, type }) {
                ErrorStore.createAtPath(expression, state[type], setReactive);
            },

            deleteError(state, { expression, type }) {
                ErrorStore.deleteAtPath(expression, state[type], deleteReactive);
            },

            resetError(state, { expression, type }) {
                const { errorName, container } = ErrorStore.getErrorContainer(expression, state[type]);
                if (container !== null && container[errorName].code !== 0) {
                    container[errorName] = new ShopwareError();
                }
            }
        };

        this.getters = {
            boundError: (state, getters) => (pointer) => {
                if (pointer === null) {
                    return new ShopwareError();
                }

                return getters.getApiError(pointer) || getters.getValidationError(pointer);
            },

            getApiError: (state) => (pointer) => {
                return ErrorStore.getFromPath(pointer, state.api);
            },

            getValidationError: (state) => (pointer) => {
                return ErrorStore.getFromPath(pointer, state.validation);
            }
        };

        this.actions = {
            registerFormField({ commit }, expression) {
                commit('createError', { expression, type: 'api' });
            },

            deleteFieldError({ commit }, expression) {
                commit('deleteError', { expression, type: 'api' });
            },

            resetFormError({ commit }, expression) {
                commit('resetError', { expression, type: 'api' });
            }
        };
    }

    setErrorData(expression, error, type = 'system') {
        this.mutations.setErrorData(this.state, { expression, error, type });

        return true;
    }
}

export default new VuexErrorStore();
