/**
 * @sw-package fundamentals@framework
 */
import ApiService from '../api.service';

class McpToolApiService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService);
        this.name = 'mcpToolService';
    }

    getTools() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get('/_action/mcp/tools', { headers })
            .then((response) => ApiService.handleResponse(response));
    }

    getCapabilities() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get('/_action/mcp/capabilities', { headers })
            .then((response) => ApiService.handleResponse(response));
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default McpToolApiService;
