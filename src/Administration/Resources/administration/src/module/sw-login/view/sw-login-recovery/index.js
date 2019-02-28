import { Component } from 'src/core/shopware';
import { email } from 'src/core/service/validation.service';
import template from './sw-login-recovery.html.twig';

Component.register('sw-login-recovery', {
    template,

    inject: ['userRecoveryService'],

    data() {
        return {
            email: ''
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
            this.$emit('isLoading');

            this.userRecoveryService.createRecovery(this.email).finally(() => {
                this.displayRecoveryInfo();
            });
        },

        displayRecoveryInfo() {
            this.$router.push({ name: 'sw.login.index.recoveryInfo' });
        }
    }
});
