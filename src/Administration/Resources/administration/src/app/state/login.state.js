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
            error: '',
            message: ''
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
        },

        loginUserWithPasswordFailure(state, payload) {
            if (!payload.response) {
                state.error = payload.message;
                state.message = `Something went wrong requesting "${payload.config.url}".`;
                return;
            }

            let data = payload.response.data.errors;
            data = data.length > 1 ? data : data[0];

            state.token = '';
            state.expiry = -1;

            state.error = data.title;
            state.message = data.detail;
        }
    }
});
