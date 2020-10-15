import template from './sw-verify-user-modal.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-verify-user-modal', {
    template,

    inject: [
        'loginService'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            confirmPassword: ''
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
        },

        onSubmitConfirmPassword() {
            return this.loginService.verifyUserToken(this.confirmPassword).then((verifiedToken) => {
                const context = { ...Shopware.Context.api };
                context.authToken.access = verifiedToken;

                const authObject = {
                    ...this.loginService.getBearerAuthentication(),
                    ...{
                        access: verifiedToken
                    }
                };

                this.loginService.setBearerAuthentication(authObject);

                this.$emit('verified', context);
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-user.user-detail.passwordConfirmation.notificationPasswordErrorTitle'),
                    message: this.$tc('sw-settings-user.user-detail.passwordConfirmation.notificationPasswordErrorMessage')
                });
            }).finally(() => {
                this.confirmPassword = '';
                this.$emit('close');
            });
        },

        // @deprecated tag:v6.4.0 use loginService.verifyUserToken() instead
        verifyUserToken() {
            // eslint-disable-next-line no-unused-vars
            return this.loginService.verifyUserToken(this.confirmPassword).catch(e => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-user.user-detail.passwordConfirmation.notificationPasswordErrorTitle'),
                    message: this.$tc('sw-settings-user.user-detail.passwordConfirmation.notificationPasswordErrorMessage')
                });
            }).finally(() => {
                this.confirmPassword = '';
            });
        },

        onCloseConfirmPasswordModal() {
            this.confirmPassword = '';
            this.$emit('close');
        }
    }
});
