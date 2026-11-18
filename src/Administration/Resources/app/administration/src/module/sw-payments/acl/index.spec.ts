/**
 * @sw-package checkout
 */
import PrivilegesService from 'src/app/service/privileges.service';

describe('src/module/sw-payments/acl', () => {
    it('stores the payment viewer key with the Shopware Payments viewer role', async () => {
        Shopware.Application.addServiceProvider('privileges', () => new PrivilegesService());

        await import('src/module/sw-settings-payment/acl');
        await import('./index');

        const privileges = Shopware.Service('privileges').getPrivilegesForAdminPrivilegeKeys(['sw-payments.viewer']);

        expect(privileges).toEqual(
            expect.arrayContaining([
                'sw-payments.viewer',
                'payment.viewer',
                'payment_method:read',
                'app.ShopwarePayments',
            ]),
        );
    });
});
