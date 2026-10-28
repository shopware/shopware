/**
 * @sw-package fundamentals@framework
 */

describe('src/module/sw-integration/acl/index.js', () => {
    const addPrivilegeMappingEntryMock = jest.fn();
    const originalShopwareService = Shopware.Service;

    beforeAll(() => {
        Shopware.Service = (() => {
            return {
                addPrivilegeMappingEntry: addPrivilegeMappingEntryMock,
            };
        }) as unknown as typeof Shopware.Service;
    });

    beforeEach(async () => {
        jest.resetAllMocks();
        jest.resetModules();

        const aclRegistration = './index';
        await import(aclRegistration);
    });

    afterAll(() => {
        Shopware.Service = originalShopwareService;
    });

    it('should grant the MCP allowlist editor every privilege the allowlist route requires', () => {
        const mcpEntry = addPrivilegeMappingEntryMock.mock.calls
            .map(([entry]: [{ key: string; roles: { editor: { privileges: string[] } } }]) => entry)
            .find((entry) => entry.key === 'integration_mcp');

        expect(mcpEntry?.roles.editor.privileges).toEqual(
            expect.arrayContaining([
                'api_action_integration_mcp-allowlist',
                'integration:update',
            ]),
        );
    });
});
