/**
 * @package admin
 */

import { email } from 'src/core/service/validation.service';
import template from './sw-login-recovery.html.twig';

const { Component } = Shopware;

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Component.register('sw-login-recovery', {
    template,

    inject: ['userRecoveryService'],

    data() {
        return {
            email: '',
        };
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            const emailField = this.$refs.swLoginRecoveryEmailField.$el.querySelector('input');

            emailField.focus();
        },

        isEmailValid() {
            return email(this.email);
        },

        sendRecoveryMail() {
            this.$emit('is-loading');

            this.userRecoveryService.createRecovery(this.email).then(() => {
                this.displayRecoveryInfo();
            }).catch(error => {
                this.displayRecoveryInfo(error.response.data);
            });
        },

        displayRecoveryInfo(data = null) {
            let seconds = 0;

            if (data !== null) {
                let error = data?.errors;

                error = Array.isArray(error) ? error[0] : error;

                if (parseInt(error?.status, 10) === 429) {
                    seconds = error?.meta?.parameters?.seconds;
                }
            }

            this.$router.push({
                name: 'sw.login.index.recoveryInfo',
                params: {
                    waitTime: seconds,
                },
            });
        },
    },
});
