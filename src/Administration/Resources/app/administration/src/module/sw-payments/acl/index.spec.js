/**
 * @sw-package checkout
 */
import PrivilegesService from 'src/app/service/privileges.service';

describe('src/module/sw-payments/acl', () => {
    beforeAll(async () => {
        Shopware.Application.addServiceProvider('privileges', () => new PrivilegesService());

        await import('src/module/sw-media/acl');
        await import('src/module/sw-settings-payment/acl');
        await import('./index');
    });

    it('stores the payment viewer key with the Shopware Payments viewer role', () => {
        const privileges = Shopware.Service('privileges').getPrivilegesForAdminPrivilegeKeys(['sw-payments.viewer']);

        expect(privileges).toEqual(
            expect.arrayContaining([
                'sw-payments.viewer',
                'payment.viewer',
                'payment_method:read',
                'app.ShopwarePayments',
            ]),
        );
        expect(privileges).not.toContain('payment.editor');
        expect(privileges).not.toContain('payment_method:update');
    });

    it('stores the payment editor key with the Shopware Payments editor role', () => {
        const privileges = Shopware.Service('privileges').getPrivilegesForAdminPrivilegeKeys(['sw-payments.editor']);

        expect(privileges).toEqual(
            expect.arrayContaining([
                'sw-payments.editor',
                'sw-payments.viewer',
                'payment.editor',
                'payment.viewer',
                'payment_method:update',
                'media:create',
            ]),
        );
    });
});
