import template from './sw-user-verification.html.twig';

/**
 * @sw-package framework
 *
 * Renders the verify user modal when the user verification service asks for it and reports the
 * outcome back, so a verification can be requested from anywhere without a modal in the caller.
 *
 * @private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['userVerificationService'],

    computed: {
        requested(): boolean {
            return Shopware.Store.get('userVerification').requested;
        },

        ssoAvailable(): boolean {
            return Shopware.Store.get('userVerification').ssoAvailable;
        },
    },

    methods: {
        onVerified(context: typeof Shopware.Context.api): void {
            this.userVerificationService.complete(context);
        },

        onClose(): void {
            // the modal also closes after a successful verification; by then nothing is pending
            this.userVerificationService.cancel();
        },

        onVerifyWithSso(): void {
            void this.userVerificationService.switchToSso();
        },
    },
});
