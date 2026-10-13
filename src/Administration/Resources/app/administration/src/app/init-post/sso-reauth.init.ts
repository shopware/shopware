/**
 * @sw-package framework
 */

import type { Router } from 'vue-router';

/**
 * Finishes a re-authentication the verify user modal started for an SSO session: the identity
 * provider sent the user back to the administration, so return to the page the modal was opened on
 * and say that the action has to be repeated.
 *
 * @private
 */
export default function initializeSsoReauth(): Promise<void> {
    const loginService = Shopware.Service('loginService');
    const ssoReauthService = Shopware.Service('ssoReauthService');

    loginService.addOnLoginListener(() => {
        if (!sessionStorage.getItem('sw-sso-session')) {
            return;
        }

        const returnRoute = ssoReauthService.complete();
        if (returnRoute === null) {
            return;
        }

        // @ts-expect-error
        const router = Shopware.Application.view?.router as null | Router;
        void router?.push(returnRoute);

        const root = Shopware.Application.getApplicationRoot();
        void Shopware.Store.get('notification').createNotification({
            variant: 'info',
            title: root ? root.$t('global.sw-verify-user-modal.ssoReauthCompletedTitle') : '',
            message: root ? root.$t('global.sw-verify-user-modal.ssoReauthCompletedMessage') : '',
        });
    });

    return Promise.resolve();
}
