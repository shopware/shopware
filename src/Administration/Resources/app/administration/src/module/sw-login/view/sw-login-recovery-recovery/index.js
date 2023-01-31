/**
 * @package admin
 */

import template from './sw-login-recovery-recovery.html.twig';

const { Component, Mixin, State } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Component.register('sw-login-recovery-recovery', {
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

    data() {
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
                this.$nextTick(() => this.$refs.swLoginRecoveryRecoveryNewPasswordField
                    .$el.querySelector('input')?.focus());
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.userRecoveryService.checkHash(this.hash).then(() => {
                this.hashValid = true;
            }).catch(() => {
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
                this.userRecoveryService.updateUserPassword(
                    this.hash,
                    this.newPassword,
                    this.newPasswordConfirm,
                ).then(() => {
                    this.$router.push({ name: 'sw.login.index' });
                }).catch((error) => {
                    State.dispatch('error/addApiError', {
                        expression: `user.${this.hash}.password`,
                        error: new Shopware.Classes.ShopwareError(error.response.data.errors[0]),
                    });

                    this.createNotificationError({
                        message: error.message,
                    });
                });
            }
        },
    },
});
