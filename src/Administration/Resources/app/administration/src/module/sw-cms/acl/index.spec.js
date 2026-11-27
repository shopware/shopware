/**
 * @sw-package discovery
 */
import PrivilegesService from 'src/app/service/privileges.service';

describe('src/module/sw-cms/acl', () => {
    beforeAll(async () => {
        Shopware.Application.addServiceProvider('privileges', () => new PrivilegesService());

        await import('src/module/sw-media/acl');
        await import('./index');
    });

    it('lets the CMS viewer read the slot translations', () => {
        const privileges = Shopware.Service('privileges').getPrivilegesForAdminPrivilegeKeys(['cms.viewer']);

        expect(privileges).toEqual(expect.arrayContaining(['cms.viewer', 'cms_slot:read', 'cms_slot_translation:read']));
    });
});
