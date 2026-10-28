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
        // the shop has an identity provider, so the modal offers it next to the password
        ssoAvailable: false,
    }),

    actions: {
        request(ssoAvailable: boolean): void {
            this.requested = true;
            this.ssoAvailable = ssoAvailable;
        },

        dismiss(): void {
            this.requested = false;
            this.ssoAvailable = false;
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
