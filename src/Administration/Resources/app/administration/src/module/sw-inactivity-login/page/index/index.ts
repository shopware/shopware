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

    props: {
        hash: {
            type: String,
            required: true,
        },
    },

    data(): {
        isLoading: boolean,
        lastKnownUser: string,
        password: string,
        passwordError: null | { detail: string },
        sessionChannel: null | BroadcastChannel,
        } {
        return {
            isLoading: false,
            lastKnownUser: '',
            password: '',
            passwordError: null,
            sessionChannel: null,
        };
    },

    computed: {
        title(): string {
            const moduleName = this.$tc('sw-inactivity-login.general.mainMenuItemIndex');
            const adminName = this.$tc('global.sw-admin-menu.textShopwareAdmin');

            return `${moduleName} | ${adminName}`;
        },
    },

    metaInfo(): MetaInfo {
        return {
            title: this.title,
        };
    },

    created() {
        const lastKnownUser = sessionStorage.getItem('lastKnownUser');

        if (!lastKnownUser) {
            void this.$router.push({ name: 'sw.login.index' });

            return;
        }

        this.sessionChannel = new BroadcastChannel('session_channel');
        this.sessionChannel.postMessage({ inactive: true });
        this.sessionChannel.onmessage = (event) => {
            const data = event.data as {inactive?: boolean};
            if (!data || !Shopware.Utils.object.hasOwnProperty(data, 'inactive')) {
                return;
            }

            if (data.inactive) {
                return;
            }

            this.forwardLogin();
            window.location.reload();
        };
        this.lastKnownUser = lastKnownUser;
    },

    mounted() {
        const dataUrl = localStorage.getItem(`inactivityBackground_${this.hash}`);
        if (!dataUrl) {
            return;
        }

        // We know this exists once the component is mounted
        (document.querySelector('.sw-inactivity-login') as HTMLElement).style.backgroundImage = `url('${dataUrl}')`;
    },

    beforeDestroy() {
        this.sessionChannel?.close();

        localStorage.removeItem(`inactivityBackground_${this.hash}`);
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
            this.forwardLogin();

            this.sessionChannel?.postMessage({ inactive: false });

            window.location.reload();
        },

        forwardLogin() {
            this.password = '';
            sessionStorage.removeItem('lastKnownUser');

            const previousRoute = JSON.parse(sessionStorage.getItem(`sw-admin-previous-route_${this.hash}`) || '{}') as {
                fullPath?: string,
                name?: string,
            };
            sessionStorage.removeItem(`sw-admin-previous-route_${this.hash}`);

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
