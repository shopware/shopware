/**
 * @sw-package fundamentals@discovery
 */

const addPrivilegeMappingEntryMock = jest.fn();

const originalShopwareService = Shopware.Service;

describe('src/module/sw-settings-language/acl/index.js', () => {
    beforeAll(() => {
        Shopware.Service = () => {
            return {
                addPrivilegeMappingEntry: addPrivilegeMappingEntryMock,
            };
        };
    });

    beforeEach(async () => {
        jest.resetAllMocks();
        jest.resetModules();

        await import('./index');
    });

    afterAll(() => {
        Shopware.Service = originalShopwareService;
    });

    it('registers the privilege mapping entry for the language module', () => {
        expect(addPrivilegeMappingEntryMock).toHaveBeenCalledTimes(1);
        expect(addPrivilegeMappingEntryMock).toHaveBeenNthCalledWith(1, {
            category: 'permissions',
            parent: 'settings',
            key: 'language',
            roles: expect.any(Object),
        });
    });

    it('maps the expected privileges and dependencies per role', () => {
        const roles = {
            viewer: { privileges: 6, dependencies: 0 },
            editor: { privileges: 2, dependencies: 1 },
            creator: { privileges: 1, dependencies: 2 },
            deleter: { privileges: 2, dependencies: 1 },
        };

        const registered = addPrivilegeMappingEntryMock.mock.calls[0][0].roles;

        expect(Object.keys(registered)).toHaveLength(Object.keys(roles).length);

        Object.keys(roles).forEach((key) => {
            expect(registered[key].privileges).toHaveLength(roles[key].privileges);
            expect(registered[key].dependencies).toHaveLength(roles[key].dependencies);
        });
    });

    it('grants the viewer the privileges the language view depends on', () => {
        const { viewer } = addPrivilegeMappingEntryMock.mock.calls[0][0].roles;

        // reading the languages themselves
        expect(viewer.privileges).toContain('language:read');
        // sales channels association shown in the listing
        expect(viewer.privileges).toContain('sales_channel:read');
        // translation metadata endpoints (list + meta)
        expect(viewer.privileges).toContain('system:translation:read');
    });

    it('grants the translation write privileges to the matching roles', () => {
        const { editor, deleter } = addPrivilegeMappingEntryMock.mock.calls[0][0].roles;

        // installing/updating community translations goes through the install route
        expect(editor.privileges).toContain('system:translation:create');
        // deleting a language also removes its downloaded translation files
        expect(deleter.privileges).toContain('system:translation:delete');
    });
});
