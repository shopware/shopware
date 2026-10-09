/**
 * @sw-package framework
 */

import template from './sw-verify-user-modal.html.twig';

const { Mixin } = Shopware;

/**
 * @private
 */
export default {
    template,

    inject: ['loginService', 'ssoSettingsService'],

    emits: ['verified', 'close'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            confirmPassword: '',
            // null until the SSO lookup resolved; the password prompt renders only for non-SSO sessions
            isSso: null,
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            try {
                this.isSso = (await this.ssoSettingsService.isSso()).isSso === true;
            } catch {
                this.isSso = false;
            }

            if (!this.isSso) {
                return;
            }

            // An SSO session has no Shopware password to verify and the user-verified scope
            // is not granted to it anyway; the identity provider already verified the user.
            this.$emit('verified', { ...Shopware.Context.api });
            this.$emit('close');
        },

        onSubmitConfirmPassword() {
            return this.loginService
                .verifyUserToken(this.confirmPassword)
                .then((verifiedToken) => {
                    const context = { ...Shopware.Context.api };
                    context.authToken.access = verifiedToken;

                    const authObject = {
                        ...this.loginService.getBearerAuthentication(),
                        access: verifiedToken,
                    };

                    this.loginService.setBearerAuthentication(authObject);

                    this.$emit('verified', context);
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t(
                            'sw-users-permissions.users.user-detail.passwordConfirmation.notificationPasswordErrorMessage',
                        ),
                    });
                })
                .finally(() => {
                    this.confirmPassword = '';
                    this.$emit('close');
                });
        },

        onCloseConfirmPasswordModal() {
            this.confirmPassword = '';
            this.$emit('close');
        },
    },
};
