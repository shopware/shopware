/**
 * @sw-package fundamentals@framework
 */
import McpToolApiService from 'src/core/service/api/mcp-tool.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createMcpToolService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const mcpToolService = new McpToolApiService(client, loginService);
    return { mcpToolService, clientMock };
}

describe('McpToolApiService', () => {
    it('has the correct service name', () => {
        const { mcpToolService } = createMcpToolService();
        expect(mcpToolService.name).toBe('mcpToolService');
    });

    it('calls the correct endpoint to fetch tools', async () => {
        const { mcpToolService, clientMock } = createMcpToolService();
        const tools = [{ name: 'shopware-system-config-read', description: 'Read system config' }];

        clientMock.onGet('/_action/mcp/tools').reply(200, { data: tools });

        await mcpToolService.getTools();

        expect(clientMock.history.get).toHaveLength(1);
        expect(clientMock.history.get[0].url).toBe('/_action/mcp/tools');
    });
});
