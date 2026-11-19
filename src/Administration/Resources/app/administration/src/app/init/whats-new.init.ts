/**
 * @private
 * @sw-package framework
 */
export default function initializeWhatsNew(): void {
    Shopware.Service('loginService').addOnLogoutListener(() => {
        Shopware.Store.get('whatsNew').$reset();
    });
}
