/**
 * @sw-package framework
 */

import template from './sw-verify-user-modal.html.twig';

const { Mixin } = Shopware;

/**
 * Asks the user for the password and emits the API context carrying the verified token.
 * Request a verification through `userVerificationService.verify()` instead of rendering this modal:
 * the service picks the password prompt or the identity provider for the current session.
 *
 * @private
 */
export default {
    template,

    inject: ['loginService'],

    emits: [
        'verified',
        'verify-with-sso',
        'close',
    ],

    mixins: [Mixin.getByName('notification')],

    props: {
        // offers the identity provider next to the password, for sessions the shop could not tell apart
        ssoAvailable: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            confirmPassword: '',
        };
    },

    methods: {
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
            this.$emit('verify-with-sso');
        },

        onCloseConfirmPasswordModal() {
            this.confirmPassword = '';
            this.$emit('close');
        },
    },
};
