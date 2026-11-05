/**
 * @sw-package framework
 */

/**
 * Whether the verify user modal is requested, see {@link UserVerificationService}.
 *
 * @private
 */
const userVerificationStore = Shopware.Store.register({
    id: 'userVerification',

    state: () => ({
        requested: false,
    }),

    actions: {
        request(): void {
            this.requested = true;
        },

        dismiss(): void {
            this.requested = false;
        },
    },
});

/**
 * @private
 */
export type UserVerificationStore = ReturnType<typeof userVerificationStore>;

/**
 * @private
 */
export default userVerificationStore;
