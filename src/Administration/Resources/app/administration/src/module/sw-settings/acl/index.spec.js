/**
 * @sw-package fundamentals@framework
 */

const addPrivilegeMappingEntryMock = jest.fn();

const originalShopwareService = Shopware.Service;

describe('src/module/sw-settings/acl/index.js', () => {
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

    it('should grant the custom field reads the product sorting criteria grid filters through', () => {
        const [entry] = addPrivilegeMappingEntryMock.mock.calls[0];

        expect(entry).toMatchObject({
            category: 'additional_permissions',
            parent: null,
            key: 'system',
        });
        expect(entry.roles.system_config.privileges).toEqual(
            expect.arrayContaining([
                'custom_field:read',
                'custom_field_set:read',
                'custom_field_set_relation:read',
            ]),
        );
    });
});
