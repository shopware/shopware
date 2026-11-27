/**
 * @sw-package discovery
 */

const addPrivilegeMappingEntry = jest.fn();

describe('src/module/sw-media/acl/index.js', () => {
    beforeEach(async () => {
        jest.resetModules();
        addPrivilegeMappingEntry.mockClear();
        jest.spyOn(Shopware, 'Service').mockImplementation(() => ({ addPrivilegeMappingEntry }));

        await import('./index');
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('lets media viewers read the product documents that use a file', () => {
        expect(addPrivilegeMappingEntry).toHaveBeenCalledTimes(1);

        const [mapping] = addPrivilegeMappingEntry.mock.calls[0];

        expect(mapping.key).toBe('media');
        expect(mapping.roles.viewer.privileges).toEqual(
            expect.arrayContaining(['product_document:read', 'product_document_translation:read']),
        );
    });
});
