/**
 * @sw-package framework
 */

import template from './sw-login-recovery-recovery.html.twig';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    inject: [
        'userRecoveryService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        hash: {
            type: String,
            required: true,
        },
    },

    data(): {
        user: {
            id: string;
            getEntityName: () => string;
        };
        newPassword: string;
        newPasswordConfirm: string;
        hashValid: boolean | null;
    } {
        return {
            // Mock an empty user so that we can send out the error
            user: {
                id: this.hash,
                getEntityName: () => 'user',
            },
            newPassword: '',
            newPasswordConfirm: '',
            hashValid: null,
        };
    },

    computed: {
        ...mapPropertyErrors('user', [
            'password',
        ]),
    },

    watch: {
        hashValid(val) {
            if (val === true) {
                void this.$nextTick(() =>
                    // @ts-expect-error
                    // eslint-disable-next-line max-len
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-return, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
                    this.$refs.swLoginRecoveryRecoveryNewPasswordField.$el.querySelector('input')?.focus(),
                );
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.userRecoveryService
                .checkHash(this.hash)
                .then(() => {
                    this.hashValid = true;
                })
                .catch(() => {
                    this.hashValid = false;
                });
        },

        validatePasswords() {
            if (this.newPassword && this.newPassword.length) {
                if (this.newPasswordConfirm && this.newPasswordConfirm.length) {
                    if (this.newPassword === this.newPasswordConfirm) {
                        return true;
                    }
                }
            }

            return false;
        },

        updatePassword() {
            if (this.validatePasswords()) {
                this.userRecoveryService
                    .updateUserPassword(this.hash, this.newPassword, this.newPasswordConfirm)
                    .then(() => {
                        void this.$router.push({ name: 'sw.login.index' });
                    })
                    .catch((error) => {
                        Shopware.Store.get('error').addApiError({
                            expression: `user.${this.hash}.password`,
                            // eslint-disable-next-line max-len
                            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-unsafe-member-access
                            error: new Shopware.Classes.ShopwareError(error.response.data.errors[0]),
                        });

                        this.createNotificationError({
                            // eslint-disable-next-line max-len
                            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access
                            message: error.message,
                        });
                    });
            }
        },
    },
});
