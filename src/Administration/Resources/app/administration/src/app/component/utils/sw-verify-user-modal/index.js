/**
 * @sw-package framework
 */

import template from './sw-verify-user-modal.html.twig';

const { Mixin } = Shopware;

/**
 * Verifies the user and emits the API context carrying the verified token. A password session confirms
 * its password; an SSO session signs in again at the identity provider, which the modal also offers
 * next to the password when the shop has one.
 * Request a verification through `userVerificationService.verify()` instead of rendering this modal.
 *
 * @private
 */
export default {
    template,

    inject: [
        'loginService',
        'userVerificationService',
    ],

    emits: [
        'verified',
        'close',
    ],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            confirmPassword: '',
            isLoading: true,
            ssoSession: false,
            ssoLoginUrl: '',
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            // loaded up front, so the SSO button opens the identity provider's window without awaiting a request
            return this.userVerificationService
                .getSsoOptions()
                .then(({ ssoSession, loginUrl }) => {
                    this.ssoSession = ssoSession;
                    this.ssoLoginUrl = loginUrl;
                })
                .finally(() => {
                    this.isLoading = false;
                });
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

        onVerifyWithSso() {
            this.confirmPassword = '';

            return this.userVerificationService
                .verifyWithSso(this.ssoLoginUrl)
                .then((context) => {
                    this.$emit('verified', context);
                    this.$emit('close');
                })
                .catch(() => {
                    // the service already told the user; the modal stays open for another try
                });
        },

        onCloseConfirmPasswordModal() {
            this.confirmPassword = '';
            this.$emit('close');
        },
    },
};
