import {
    SET_WORKING,
    LOGIN_USER_WITH_PASSWORD,
    LOGIN_USER_WITH_PASSWORD_SUCCESS,
    LOGIN_USER_WITH_PASSWORD_FAILURE
} from './types';

Shopware.State.register('login', {
    state() {
        return {
            isWorking: false,

            username: '',
            password: '',

            token: '',
            expiry: -1,

            error: '',
            message: ''
        };
    },

    actions: {
        async [LOGIN_USER_WITH_PASSWORD]({ state, commit }) {
            const providerContainer = Shopware.Application.getContainer('service');
            const loginService = providerContainer.loginService;

            commit(SET_WORKING, true);

            loginService.loginByUsername(state.username, state.password)
                .then((response) => {
                    loginService.setBearerAuthentication(response.data.token, response.data.expiry);
                    commit(LOGIN_USER_WITH_PASSWORD_SUCCESS, response);
                })
                .catch((response) => commit(LOGIN_USER_WITH_PASSWORD_FAILURE, response));
        }
    },

    mutations: {
        [SET_WORKING](state, payload) {
            state.isWorking = payload;
        },
        [LOGIN_USER_WITH_PASSWORD_SUCCESS](state, payload) {
            state.token = payload.data.token;
            state.expiry = payload.data.expiry;
            state.isWorking = false;
        },
        [LOGIN_USER_WITH_PASSWORD_FAILURE](state, payload) {
            if (!payload.response) {
                state.error = payload.message;
                state.message = `Something went wrong requesting "${payload.config.url}".`;
                state.isWorking = false;
                return;
            }

            let data = payload.response.data.errors;
            data = data.length > 1 ? data : data[0];

            state.token = '';
            state.expiry = -1;

            state.error = data.title;
            state.message = data.detail;
            state.isWorking = false;
        }
    }
});
