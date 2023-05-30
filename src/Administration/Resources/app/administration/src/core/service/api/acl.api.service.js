/**
 * @package admin
 */

class AclApiService {
    constructor(httpClient, loginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
        this.name = 'aclApiService';
    }

    additionalPrivileges() {
        const headers = this.getHeaders();
        return this.httpClient.get('/_action/acl/additional_privileges', { headers }).then((response) => {
            return Object.values(response.data);
        });
    }

    allPrivileges() {
        const headers = this.getHeaders();
        return this.httpClient.get('/_action/index', {}, { headers }).then((response) => {
            return Object.values(response.data);
        });
    }

    getHeaders() {
        return {
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json',
        };
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default AclApiService;
