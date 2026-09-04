/**
 * @sw-package fundamentals@framework
 */

const addPrivilegeMappingEntryMock = jest.fn();

const originalShopwareService = Shopware.Service;

describe('src/module/sw-settings-basic-information/acl/index.js', () => {
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

    it('registers the privilege mapping entry for cookie consent logging', () => {
        expect(addPrivilegeMappingEntryMock).toHaveBeenCalledTimes(1);
        expect(addPrivilegeMappingEntryMock).toHaveBeenNthCalledWith(1, {
            category: 'permissions',
            parent: 'settings',
            key: 'cookie_consent',
            roles: expect.any(Object),
        });
    });

    it('offers a viewer role only, the evidence tables reject writes', () => {
        const { roles } = addPrivilegeMappingEntryMock.mock.calls[0][0];

        expect(Object.keys(roles)).toEqual(['viewer']);
        expect(roles.viewer.dependencies).toHaveLength(0);
    });

    it('grants read on the log and on the snapshots it references', () => {
        const { viewer } = addPrivilegeMappingEntryMock.mock.calls[0][0].roles;

        // the decisions themselves
        expect(viewer.privileges).toContain('cookie_consent_log:read');
        // without the banner snapshot a decision only names groups it cannot explain
        expect(viewer.privileges).toContain('cookie_consent_config_version:read');
        expect(viewer.privileges).toHaveLength(2);
    });

    it('grants no privilege that would let a role change evidence', () => {
        const { viewer } = addPrivilegeMappingEntryMock.mock.calls[0][0].roles;

        viewer.privileges.forEach((privilege) => {
            expect(privilege).toMatch(/:read$/);
        });
    });
});
