import { email } from 'src/core/service/validation.service';
import template from './sw-login-recovery.html.twig';

const { Component } = Shopware;

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

            this.userRecoveryService.createRecovery(this.email).finally(() => {
                this.displayRecoveryInfo();
            });
        },

        displayRecoveryInfo() {
            this.$router.push({ name: 'sw.login.index.recoveryInfo' });
        },
    },
});
