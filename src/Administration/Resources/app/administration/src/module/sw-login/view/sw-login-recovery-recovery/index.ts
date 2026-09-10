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

    emits: [
        'is-loading',
        'is-not-loading',
    ],

    props: {
        hash: {
            type: String,
            required: true,
        },
    },

    data(): {
        user: {
            id: EntityKey<'user'>;
            getEntityName: () => string;
        };
        newPassword: string;
        newPasswordConfirm: string;
        hashValid: boolean | null;
    } {
        return {
            // Mock an empty user so that we can send out the error
            user: {
                id: this.hash as EntityKey<'user'>,
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

                    void this.$router.replace({
                        name: 'sw.login.index.recovery',
                        state: {
                            linkExpired: true,
                        },
                    });
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
            if (!this.validatePasswords()) {
                return;
            }

            this.$emit('is-loading');

            this.userRecoveryService
                .updateUserPassword(this.hash, this.newPassword, this.newPasswordConfirm)
                .then(() => {
                    void this.$router.push({ name: 'sw.login.index' });
                })
                .catch((error: unknown) => {
                    /* eslint-disable @typescript-eslint/no-unsafe-member-access */
                    // @ts-expect-error
                    let apiError = error?.response?.data?.errors as unknown;
                    apiError = Array.isArray(apiError) ? apiError[0] : undefined;
                    /* eslint-enable @typescript-eslint/no-unsafe-member-access */

                    if (apiError) {
                        Shopware.Store.get('error').addApiError({
                            expression: `user.${this.hash}.password`,
                            error: new Shopware.Classes.ShopwareError(apiError),
                        });
                    }

                    this.createNotificationError({
                        // @ts-expect-error
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                        message: error?.message,
                    });
                })
                .finally(() => {
                    this.$emit('is-not-loading');
                });
        },
    },
});
