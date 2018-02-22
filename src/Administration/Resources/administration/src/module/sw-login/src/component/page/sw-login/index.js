import { Component } from 'src/core/shopware';
import template from './sw-login.html.twig';
import './sw-login.less';

Component.register('sw-login', {
    data() {
        return {
            isLoading: false
        };
    },

    computed: {
        username: {
            get() {
                return this.$store.state.login.username;
            },
            set(value) {
                this.$store.commit('login/setUserName', value);
            }
        },
        password: {
            get() {
                return this.$store.state.login.password;
            },
            set(value) {
                this.$store.commit('login/setUserPassword', value);
            }
        },
        errorTitle() {
            return this.$store.state.login.errorTitle;
        },
        errorMessage() {
            return this.$store.state.login.errorMessage;
        }
    },

    methods: {
        loginUserWithPassword() {
            this.isLoading = true;

            return this.$store.dispatch('login/loginUserWithPassword').then((success) => {
                this.isLoading = false;

                if (success === true) {
                    this.forwardLogin();
                }
            });
        },

        forwardLogin() {
            if (!this.$store.state.login.token.length ||
                this.$store.state.login.expiry === -1) {
                return;
            }

            this.$router.push({
                name: 'core'
            });
        }
    },

    template
});
