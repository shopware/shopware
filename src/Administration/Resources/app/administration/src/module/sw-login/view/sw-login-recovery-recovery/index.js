import template from './sw-login-recovery-recovery.html.twig';

const { Component } = Shopware;

Component.register('sw-login-recovery-recovery', {
    template,

    inject: ['userRecoveryService'],

    props: {
        hash: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            newPassword: '',
            newPasswordConfirm: '',
            hashValid: null,
        };
    },

    watch: {
        hashValid(val) {
            if (val === true) {
                this.$nextTick(() => this.$refs.swLoginRecoveryRecoveryNewPasswordField
                    .$el.querySelector('input').focus());
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
                    this.hash, this.newPassword,
                    this.newPasswordConfirm,
                ).then(() => {
                    this.$router.push({ name: 'sw.login.index' });
                }).catch((error) => {
                    this.createNotificationError({
                        message: error.message,
                    });
                });
            }
        },
    },
});
