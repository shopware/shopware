/**
 * @sw-package discovery
 */
import PrivilegesService from 'src/app/service/privileges.service';

describe('src/module/sw-category/acl', () => {
    beforeAll(async () => {
        Shopware.Application.addServiceProvider('privileges', () => new PrivilegesService());

        await import('src/module/sw-media/acl');
        await import('src/module/sw-cms/acl');
        await import('./index');
    });

    it('lets the category viewer read the sales channel domains of the SEO tab', () => {
        const privileges = Shopware.Service('privileges').getPrivilegesForAdminPrivilegeKeys(['category.viewer']);

        expect(privileges).toEqual(expect.arrayContaining(['category.viewer', 'sales_channel_domain:read']));
    });

    it('lets the landing page viewer read the CMS slot translations of the base tab', () => {
        const privileges = Shopware.Service('privileges').getPrivilegesForAdminPrivilegeKeys(['landing_page.viewer']);

        expect(privileges).toEqual(expect.arrayContaining(['landing_page.viewer', 'cms_slot_translation:read']));
        expect(privileges).not.toContain('cms.viewer');
    });
});
