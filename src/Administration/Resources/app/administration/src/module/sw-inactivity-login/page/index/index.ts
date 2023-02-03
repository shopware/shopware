import './sw-inactivity-login.scss';
import type { MetaInfo } from 'vue-meta';
import template from './sw-inactivity-login.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 */
Component.register('sw-inactivity-login', {
    template,

    inject: ['loginService'],

    data(): {
        isLoading: boolean,
        lastKnownUser: string,
        password: string,
        passwordError: null|{ detail: string }
        } {
        return {
            isLoading: false,
            lastKnownUser: '',
            password: '',
            passwordError: null,
        };
    },

    metaInfo(): MetaInfo {
        return {
            title: this.$createTitle(),
        };
    },

    created() {
        const lastKnownUser = localStorage.getItem('lastKnownUser');
        localStorage.removeItem('lastKnownUser');

        if (!lastKnownUser) {
            void this.$router.push({ name: 'sw.login.index' });

            return;
        }

        this.lastKnownUser = lastKnownUser;
    },

    mounted() {
        const dataUrl = localStorage.getItem('inactivityBackground');
        if (!dataUrl) {
            return;
        }

        // We know this exists once the component is mounted
        (document.querySelector('.sw-inactivity-login') as HTMLElement).style.backgroundImage = `url('${dataUrl}')`;
    },

    beforeDestroy() {
        localStorage.removeItem('inactivityBackground');
    },

    methods: {
        loginUserWithPassword() {
            this.isLoading = true;

            return this.loginService.loginByUsername(this.lastKnownUser, this.password)
                .then(() => {
                    this.handleLoginSuccess();
                    this.isLoading = false;
                })
                .catch(() => {
                    this.password = '';

                    this.passwordError = {
                        detail: this.$tc('sw-inactivity-login.modal.errors.password'),
                    };

                    this.isLoading = false;
                });
        },

        handleLoginSuccess() {
            this.password = '';

            this.forwardLogin();

            window.location.reload();
        },

        forwardLogin() {
            const previousRoute = JSON.parse(sessionStorage.getItem('sw-admin-previous-route') || '') as {
                fullPath?: string,
                name?: string,
            };
            sessionStorage.removeItem('sw-admin-previous-route');

            if (previousRoute?.fullPath) {
                void this.$router.push(previousRoute.fullPath);
                return;
            }

            void this.$router.push({ name: 'core' });
        },

        onBackToLogin() {
            this.isLoading = true;
            this.lastKnownUser = '';
            this.password = '';

            void this.$router.push({ name: 'sw.login.index' });
        },
    },
});
