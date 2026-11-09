import PrivilegesService from 'src/app/service/privileges.service';

/**
 * @sw-package checkout
 */

describe('order privileges', () => {
    beforeAll(async () => {
        Shopware.Application.addServiceProvider('privileges', () => new PrivilegesService());
        await import('./index');
    });

    it.each([
        ['order.viewer', false, false],
        ['order.editor', true, true],
        ['order.deleter', true, false],
        ['order.creator', true, true],
    ])('grants the required delete privileges to %s', (role, deletesOrders, deletesVersions) => {
        const privileges = Shopware.Service('privileges').getPrivilegesForAdminPrivilegeKeys([role]);

        expect(privileges.includes('order:delete')).toBe(deletesOrders);
        expect(privileges.includes('version:delete')).toBe(deletesVersions);
    });
});
