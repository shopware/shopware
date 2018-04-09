import { State } from 'src/core/shopware';

/**
 * @module app/state/login
 */
State.register('login', {
    namespaced: true,
    strict: true,

    state() {
        return {
            username: '',
            password: '',
            token: '',
            expiry: -1,
            errorTitle: '',
            errorMessage: ''
        };
    },

    actions: {
        loginUserWithPassword({ state, commit }) {
            const providerContainer = Shopware.Application.getContainer('service');
            const loginService = providerContainer.loginService;

            return loginService.loginByUsername(state.username, state.password)
                .then((response) => {
                    loginService.setBearerAuthentication(response.data.token, response.data.expiry);
                    commit('loginUserWithPasswordSuccess', response);

                    return true;
                })
                .catch((response) => {
                    commit('loginUserWithPasswordFailure', response);
                    return false;
                });
        }
    },

    mutations: {
        setUserName(state, userName) {
            state.username = userName;
        },

        setUserPassword(state, userPassword) {
            state.password = userPassword;
        },

        loginUserWithPasswordSuccess(state, payload) {
            state.token = payload.data.token;
            state.expiry = payload.data.expiry;
            state.errorTitle = '';
            state.errorMessage = '';
            state.password = '';
        },

        loginUserWithPasswordFailure(state, payload) {
            if (!payload.response) {
                state.errorTitle = payload.message;
                state.errorMessage = `Something went wrong requesting "${payload.config.url}".`;
                return;
            }

            let error = payload.response.data.error;
            error = error.length > 1 ? error : error[0];

            state.token = '';
            state.expiry = -1;

            state.errorTitle = error.title;
            state.errorMessage = error.detail;
            state.password = '';
        }
    }
});
