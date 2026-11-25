/**
 * @sw-package inventory
 */

const addPrivilegeMappingEntryMock = jest.fn();
const getPrivilegesMock = jest.fn((role) => [`${role}:privilege`]);

const originalShopwareService = Shopware.Service;

describe('src/module/sw-product/acl/index.js', () => {
    beforeAll(() => {
        Shopware.Service = () => {
            return {
                addPrivilegeMappingEntry: addPrivilegeMappingEntryMock,
                getPrivileges: getPrivilegesMock,
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

    it('should grant the product document privileges to the viewer and editor roles', () => {
        expect(addPrivilegeMappingEntryMock).toHaveBeenCalledTimes(1);

        const registeredRoles = addPrivilegeMappingEntryMock.mock.calls[0][0].roles;

        expect(registeredRoles.viewer.privileges).toEqual(
            expect.arrayContaining(['product_document:read', 'product_document_translation:read']),
        );
        expect(registeredRoles.editor.privileges).toEqual(
            expect.arrayContaining(['product_document:create', 'product_document:update', 'product_document:delete']),
        );
    });
});
