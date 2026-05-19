/**
 * @sw-package discovery
 */
import PrivilegesService from 'src/app/service/privileges.service';

Shopware.Application.addServiceProvider('privileges', () => {
    return new PrivilegesService();
});

describe('sw-theme-manager acl', () => {
    it('registers theme privilege mapping', () => {
        const privilegeService = Shopware.Service('privileges');
        const addSpy = jest.spyOn(privilegeService, 'addPrivilegeMappingEntry');
        const getPrivilegesSpy = jest.spyOn(privilegeService, 'getPrivileges')
            .mockImplementation((key) => {
                if (key === 'media.viewer') {
                    return () => ['media.viewer'];
                }

                if (key === 'media.creator') {
                    return () => ['media.creator'];
                }

                return () => [key];
            });

        jest.isolateModules(() => {
            require('./index');
        });

        expect(getPrivilegesSpy).toHaveBeenCalledWith('media.viewer');
        expect(getPrivilegesSpy).toHaveBeenCalledWith('media.creator');

        expect(addSpy).toHaveBeenCalledWith(expect.objectContaining({
            key: 'theme',
            roles: expect.objectContaining({
                viewer: expect.objectContaining({
                    privileges: expect.arrayContaining([
                        'theme:read',
                        'sales_channel:read',
                        expect.any(Function),
                    ]),
                }),
                editor: expect.objectContaining({
                    privileges: expect.arrayContaining([
                        'theme:update',
                        expect.any(Function),
                    ]),
                    dependencies: ['theme.viewer'],
                }),
                creator: expect.objectContaining({
                    privileges: expect.arrayContaining(['theme:create']),
                    dependencies: ['theme.viewer', 'theme.editor'],
                }),
                deleter: expect.objectContaining({
                    privileges: expect.arrayContaining(['theme:delete']),
                    dependencies: ['theme.viewer'],
                }),
            }),
        }));

        getPrivilegesSpy.mockRestore();
    });
});
