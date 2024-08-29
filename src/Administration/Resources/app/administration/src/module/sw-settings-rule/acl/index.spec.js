/**
 * @package services-settings
 * @group disabledCompat
 */

const addPrivilegeMappingEntryMock = jest.fn();

const originalShopwareService = Shopware.Service;

describe('src/module/sw-settings-rule/acl/index.js', () => {
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

    it('should register privilege mapping entry', () => {
        const basicInformation = {
            category: 'permissions',
            parent: 'settings',
            key: 'rule',
        };

        expect(addPrivilegeMappingEntryMock).toHaveBeenNthCalledWith(1, {
            ...basicInformation,
            roles: expect.any(Object),
        });
    });

    it('should privilege roles', () => {
        const roles = {
            viewer: {
                privileges: 29,
                dependencies: 0,
            },
            editor: {
                privileges: 12,
                dependencies: 1,
            },
            creator: {
                privileges: 1,
                dependencies: 2,
            },
            deleter: {
                privileges: 1,
                dependencies: 1,
            },
        };

        expect(addPrivilegeMappingEntryMock).toHaveBeenCalledTimes(1);
        const registered = addPrivilegeMappingEntryMock.mock.calls[0][0].roles;

        expect(registered).toBeDefined();
        expect(Object.keys(registered)).toHaveLength(Object.keys(roles).length);

        Object.keys(roles).forEach((key) => {
            const role = registered[key];
            expect(role).toBeDefined();

            expect(role.privileges).toHaveLength(roles[key].privileges);
            expect(role.dependencies).toHaveLength(roles[key].dependencies);
        });
    });
});
